import {
  ActionRowBuilder,
  Client,
  Events,
  GatewayIntentBits,
  ModalBuilder,
  REST,
  Routes,
  SlashCommandBuilder,
  StringSelectMenuBuilder,
  TextInputBuilder,
  TextInputStyle,
} from "discord.js";
import fs from "node:fs";

const DISCORD_BOT_TOKEN = (process.env.DISCORD_BOT_TOKEN || "").trim();
const DISCORD_GUILD_ID = (process.env.DISCORD_GUILD_ID || "").trim();
const BOT_INTERNAL_URL = (process.env.BOT_INTERNAL_URL || "http://nginx").trim().replace(/\/+$/, "");
const BOT_PUBLIC_URL = (process.env.BOT_PUBLIC_URL || "").trim().replace(/\/+$/, "");
const BOT_API_TOKEN = (process.env.BOT_API_TOKEN || "").trim();

const POLL_SECONDS = Math.max(5, parseInt(process.env.BOT_POLL_SECONDS || "30", 10) || 30);
const COOLDOWN_SECONDS = Math.max(60, parseInt(process.env.BOT_REMIND_COOLDOWN_SECONDS || "600", 10) || 600);

const STATE_PATH = "/tmp/dueldesk-discord-bot-state.json";

// Single shared state for poll loop + command flows.
// Avoids poll loop overwriting `flows` with a stale snapshot.
const STATE = loadState();
const SESSIONS = new Map(); // ephemeral flows (memory-only)

function loadState() {
  try {
    const raw = fs.readFileSync(STATE_PATH, "utf8");
    const data = JSON.parse(raw);
    if (data && typeof data === "object") return data;
  } catch {}
  return { sent: {} };
}

function saveState(state) {
  try {
    fs.writeFileSync(STATE_PATH, JSON.stringify(state), "utf8");
  } catch {}
}

async function fetchJson(path) {
  const url = `${BOT_INTERNAL_URL}${path}`;
  const res = await fetch(url, {
    method: "GET",
    headers: {
      Authorization: `Bearer ${BOT_API_TOKEN}`,
      Accept: "application/json",
    },
  });
  if (!res.ok) {
    const body = await res.text().catch(() => "");
    const err = new Error(`HTTP ${res.status} ${url} ${body}`.slice(0, 600));
    err.status = res.status;
    err.body = body;
    err.url = url;
    throw err;
  }
  return await res.json();
}

async function postJson(path, payload) {
  const url = `${BOT_INTERNAL_URL}${path}`;
  const res = await fetch(url, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${BOT_API_TOKEN}`,
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify(payload),
  });
  const text = await res.text().catch(() => "");
  let data = null;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {}
  if (!res.ok) {
    const msg = (data && data.error) ? data.error : text;
    const err = new Error(`HTTP ${res.status} ${msg}`.slice(0, 600));
    err.status = res.status;
    err.body = text;
    err.url = url;
    throw err;
  }
  return data;
}

function friendlyApiError(e) {
  const status = e?.status;
  const body = typeof e?.body === "string" ? e.body : "";
  if (status === 401) {
    return "Erreur 401: le bot n'est pas autorise a appeler l'API DuelDesk (verifie `BOT_API_TOKEN`).";
  }
  if (status === 403) {
    if (body.includes("Forbidden")) {
      return (
        "Acces refuse (403): ton compte Discord n'est pas lie a un compte **admin** DuelDesk.\n" +
        "1) Connecte-toi sur le site avec ton compte admin\n" +
        "2) Va sur `/account` -> Connecter Discord\n" +
        "3) Verifie ensuite dans `/admin/users` que ce user a le role `admin`"
      );
    }
    return "Acces refuse (403).";
  }
  return null;
}

function taskText(t) {
  const title = (t.tournament_name || "Tournoi").trim();
  const a = (t.a || "A").trim();
  const b = (t.b || "B").trim();
  const bo = t.best_of ? `BO${t.best_of}` : "BO?";
  const step = (t.next_step || "pickban").toString();
  const slot = t.next_slot ? `slot ${t.next_slot}` : "slot ?";
  const base = BOT_PUBLIC_URL || BOT_INTERNAL_URL;
  const url = t.match_url ? `${base}${t.match_url}` : base;

  let action = "Pick/Ban";
  if (step === "coin_toss") action = "Pile ou face";
  else if (step === "start") action = "Choix Team A/B";
  else if (step === "side") action = "Choix du side";
  else if (step === "ban") action = "Ban";
  else if (step === "pick") action = "Pick";

  return `DuelDesk: ${action} requis (${title})\nMatch: ${a} vs ${b} (${bo})\nProchain: ${step} (${slot})\nLien: ${url}`;
}

async function dmUser(client, discordUserId, content) {
  const userId = String(discordUserId || "").trim();
  if (!/^\d{17,20}$/.test(userId)) return false;
  const user = await client.users.fetch(userId);
  if (!user) return false;
  await user.send({ content });
  return true;
}

async function pollOnce(client, state) {
  const data = await fetchJson("/api/bot/pickban/pending");
  if (!data || data.ok !== true || !Array.isArray(data.tasks)) return;

  if (data.tasks.length > 0) {
    console.log(`pickban: ${data.tasks.length} task(s) pending`);
  }

  const now = Math.floor(Date.now() / 1000);

  for (const t of data.tasks) {
    const key = (t.task_key || "").toString();
    if (!key) continue;

    const last = state.sent[key] || 0;
    if (now - last < COOLDOWN_SECONDS) continue;

    const discordIds = Array.isArray(t.discord_user_ids) ? t.discord_user_ids : [];
    if (discordIds.length === 0) {
      console.log(`pickban: skip ${key} (no discord_user_ids; are captains linked?)`);
      continue;
    }

    const msg = taskText(t);

    let ok = 0;
    for (const did of discordIds) {
      const userId = String(did || "").trim();
      if (!/^\d{17,20}$/.test(userId)) {
        console.log(`pickban: skip invalid discord user id: ${userId || "(empty)"}`);
        continue;
      }
      try {
        await dmUser(client, userId, msg);
        ok++;
      } catch (e) {
        console.log(`pickban: DM failed for ${userId}: ${(e && e.message) ? e.message : String(e)}`.slice(0, 180));
      }
    }

    if (ok > 0) {
      state.sent[key] = now;
    }
  }

  saveState(state);
}

function parseScoreString(s) {
  const text = (s || "").toString().trim();
  const m = text.match(/^(\d{1,2})\s*-\s*(\d{1,2})$/);
  if (!m) return null;
  const a = parseInt(m[1], 10);
  const b = parseInt(m[2], 10);
  if (!Number.isFinite(a) || !Number.isFinite(b)) return null;
  if (a === b) return null;
  return [a, b];
}

function sessionKey(interaction) {
  return `${interaction.user.id}:${interaction.channelId || "dm"}`;
}

function menuId(kind, ownerId, payload = "") {
  // Use a delimiter that won't conflict with guild/channel/user IDs.
  return `dd|${kind}|${ownerId}|${payload}`;
}

function modalId(kind, ownerId, payload = "") {
  return `dd|${kind}|${ownerId}|${payload}`;
}

async function deploySlashCommands(appId) {
  const commands = [
    new SlashCommandBuilder().setName("ping").setDescription("Verifier que le bot est en ligne"),
    new SlashCommandBuilder().setName("report").setDescription("Reporter un score (admin)"),
    new SlashCommandBuilder().setName("pickban").setDescription("Lister les pick/ban pending (admin)"),
    new SlashCommandBuilder().setName("cancel").setDescription("Annuler une action en cours"),
  ].map((c) => c.toJSON());

  if (!DISCORD_GUILD_ID) {
    console.log("slash: DISCORD_GUILD_ID manquant; skip deploy commands (global deploy non supporte ici).");
    return;
  }

  const rest = new REST({ version: "10" }).setToken(DISCORD_BOT_TOKEN);
  await rest.put(Routes.applicationGuildCommands(appId, DISCORD_GUILD_ID), { body: commands });
  console.log(`slash: deployed ${commands.length} guild command(s) to ${DISCORD_GUILD_ID}`);
}

function onlyOwner(interaction, ownerId) {
  if (interaction.user.id === ownerId) return true;
  // Best-effort: don't let others click someone else's ephemeral UI.
  interaction.reply({ content: "Ce menu n'est pas pour toi.", ephemeral: true }).catch(() => {});
  return false;
}

function buildTournamentMenu(ownerId, tournaments) {
  const list = Array.isArray(tournaments) ? tournaments : [];
  const limited = list.slice(0, 25);

  const menu = new StringSelectMenuBuilder()
    .setCustomId(menuId("report_tournament", ownerId))
    .setPlaceholder("Choisis un tournoi")
    .addOptions(
      limited.map((t) => ({
        label: `#${t.id} ${String(t.name || "").slice(0, 80)}`.slice(0, 100),
        value: String(t.id),
        description: String(t.status || "").slice(0, 100),
      }))
    );

  return new ActionRowBuilder().addComponents(menu);
}

function buildMatchMenu(ownerId, tournamentId, matches) {
  const list = Array.isArray(matches) ? matches : [];
  const limited = list.slice(0, 25);

  const menu = new StringSelectMenuBuilder()
    .setCustomId(menuId("report_match", ownerId, String(tournamentId)))
    .setPlaceholder("Choisis un match")
    .addOptions(
      limited.map((m) => {
        const tag = m.tag ? ` ${m.tag}` : "";
        const bo = m.best_of ? `BO${m.best_of}` : "";
        const label = `#${m.id}${tag}: ${String(m.a || "A")} vs ${String(m.b || "B")}`.slice(0, 100);
        return {
          label,
          value: String(m.id),
          description: `${bo}`.slice(0, 100),
        };
      })
    );

  return new ActionRowBuilder().addComponents(menu);
}

async function main() {
  if (!DISCORD_BOT_TOKEN) throw new Error("Missing env DISCORD_BOT_TOKEN");
  if (!BOT_API_TOKEN) throw new Error("Missing env BOT_API_TOKEN");

  const client = new Client({
    intents: [GatewayIntentBits.Guilds],
  });

  client.once(Events.ClientReady, async () => {
    console.log(`Bot ready: ${client.user?.tag || "unknown"}`);
    try {
      const app = client.application ? await client.application.fetch() : null;
      const appId = app?.id || client.application?.id;
      if (appId) await deploySlashCommands(appId);
      else console.log("slash: missing client.application.id; cannot deploy commands");
    } catch (e) {
      console.error(`slash: deploy failed: ${String(e?.message || e)}`.slice(0, 300));
    }
  });

  client.on(Events.InteractionCreate, async (interaction) => {
    try {
      if (interaction.isChatInputCommand()) {
        const name = interaction.commandName;

        if (name === "ping") {
          await interaction.reply({ content: "pong", ephemeral: true });
          return;
        }

        if (name === "cancel") {
          SESSIONS.delete(sessionKey(interaction));
          await interaction.reply({ content: "OK (annule).", ephemeral: true });
          return;
        }

        if (name === "pickban") {
          await interaction.deferReply({ ephemeral: true });
          try {
            const me = interaction.user.id;
            const qs = new URLSearchParams({ discord_user_id: me });
            // Admin-only; endpoint will 403 if not admin.
            await fetchJson(`/api/bot/tournaments/report?${qs.toString()}`);

            const data = await fetchJson("/api/bot/pickban/pending");
            const tasks = (data && Array.isArray(data.tasks)) ? data.tasks : [];
            if (tasks.length === 0) {
              await interaction.editReply("Aucun pick/ban pending (d'apres le bot).");
              return;
            }

            const lines = tasks.slice(0, 15).map((t, i) => {
              const ids = Array.isArray(t.discord_user_ids) ? t.discord_user_ids.length : 0;
              const step = (t.next_step || "pickban").toString();
              return `${i + 1}) match #${t.match_id} (${step}) -> ${ids} DM`;
            });
            await interaction.editReply(`Pick/Ban pending: ${tasks.length}\n` + lines.join("\n"));
          } catch (e) {
            const friendly = friendlyApiError(e);
            await interaction.editReply(friendly || `Erreur: ${String(e?.message || e)}`.slice(0, 1900));
          }
          return;
        }

        if (name === "report") {
          await interaction.deferReply({ ephemeral: true });
          try {
            const me = interaction.user.id;
            const qs = new URLSearchParams({ discord_user_id: me });
            const data = await fetchJson(`/api/bot/tournaments/report?${qs.toString()}`);
            const tournaments = (data && Array.isArray(data.tournaments)) ? data.tournaments : [];

            if (tournaments.length === 0) {
              await interaction.editReply("Aucun tournoi en cours avec des matchs reportables.");
              return;
            }

            SESSIONS.set(sessionKey(interaction), { step: "pick_tournament", created_at: Date.now() });

            const row = buildTournamentMenu(me, tournaments);
            const note = tournaments.length > 25 ? "\nNote: liste tronquee (25 max)." : "";
            await interaction.editReply({
              content: "Choisis un tournoi:" + note + "\n\n`/cancel` pour annuler.",
              components: [row],
            });
          } catch (e) {
            const friendly = friendlyApiError(e);
            await interaction.editReply(friendly || `Erreur: ${String(e?.message || e)}`.slice(0, 1900));
          }
          return;
        }

        return;
      }

      if (interaction.isStringSelectMenu()) {
        const [ns, kind, ownerId, payload] = String(interaction.customId || "").split("|", 4);
        if (ns !== "dd") return;
        if (!kind || !ownerId) return;
        if (!onlyOwner(interaction, ownerId)) return;

        if (kind === "report_tournament") {
          const tournamentId = String(interaction.values?.[0] || "").trim();
          if (!/^\d+$/.test(tournamentId)) {
            await interaction.reply({ content: "Tournoi invalide.", ephemeral: true });
            return;
          }

          await interaction.deferUpdate();

          const me = interaction.user.id;
          const qs = new URLSearchParams({ discord_user_id: me });
          const data = await fetchJson(`/api/bot/tournaments/${tournamentId}/matches/report?${qs.toString()}`);
          const matches = (data && Array.isArray(data.matches)) ? data.matches : [];
          const blocked = data && Number.isFinite(data.blocked_pickban) ? data.blocked_pickban : 0;

          if (matches.length === 0) {
            const extra = blocked > 0 ? ` (${blocked} bloque(s) par pick/ban non verrouille).` : ".";
            await interaction.editReply({ content: "Aucun match reportable" + extra, components: [] });
            return;
          }

          SESSIONS.set(sessionKey(interaction), {
            step: "pick_match",
            tournament_id: tournamentId,
            created_at: Date.now(),
          });

          const row = buildMatchMenu(me, tournamentId, matches);
          const note = matches.length > 25 ? "\nNote: liste tronquee (25 max)." : "";
          const warn = blocked > 0 ? `\nNote: ${blocked} match(s) bloques car pick/ban pas verrouille.` : "";
          await interaction.editReply({
            content: `Choisis un match:${note}${warn}\n\n\`/cancel\` pour annuler.`,
            components: [row],
          });
          return;
        }

        if (kind === "report_match") {
          const tournamentId = String(payload || "").trim();
          const matchId = String(interaction.values?.[0] || "").trim();
          if (!tournamentId || !/^\d+$/.test(tournamentId) || !/^\d+$/.test(matchId)) {
            await interaction.reply({ content: "Match invalide.", ephemeral: true });
            return;
          }

          const modal = new ModalBuilder()
            .setCustomId(modalId("report_score", ownerId, `${tournamentId},${matchId}`))
            .setTitle("Reporter un score");

          const input = new TextInputBuilder()
            .setCustomId("score")
            .setLabel("Score (A-B)")
            .setStyle(TextInputStyle.Short)
            .setPlaceholder("2-1")
            .setRequired(true)
            .setMaxLength(10);

          modal.addComponents(new ActionRowBuilder().addComponents(input));
          await interaction.showModal(modal);
          return;
        }
      }

      if (interaction.isModalSubmit()) {
        const custom = String(interaction.customId || "");
        const [ns, kind, ownerId, payload] = custom.split("|", 4);
        if (ns !== "dd" || kind !== "report_score" || !ownerId || !payload) return;
        const [tournamentId, matchId] = payload.split(",", 2);
        if (!tournamentId || !matchId) return;
        if (!onlyOwner(interaction, ownerId)) return;

        const scoreRaw = interaction.fields.getTextInputValue("score");
        const scores = parseScoreString(scoreRaw);
        if (!scores) {
          await interaction.reply({ content: "Score invalide. Exemple: `2-1`.", ephemeral: true });
          return;
        }

        const [score1, score2] = scores;
        const winnerSlot = score1 > score2 ? 1 : 2;

        await interaction.deferReply({ ephemeral: true });
        try {
          const res = await postJson("/api/bot/matches/report", {
            discord_user_id: interaction.user.id,
            tournament_id: parseInt(tournamentId, 10),
            match_id: parseInt(matchId, 10),
            winner_slot: winnerSlot,
            score1,
            score2,
          });

          SESSIONS.delete(sessionKey(interaction));

          if (res && res.ok === true) {
            await interaction.editReply(`OK: match #${matchId} confirme (${score1}-${score2}).`);
            return;
          }

          await interaction.editReply("Erreur inconnue.");
        } catch (e) {
          await interaction.editReply(`Erreur: ${(e && e.message) ? e.message : String(e)}`.slice(0, 1900));
        }
      }
    } catch (e) {
      const msg = `Erreur: ${String(e?.message || e)}`.slice(0, 1800);
      try {
        if (interaction.isRepliable()) {
          if (interaction.deferred || interaction.replied) {
            await interaction.editReply(msg);
          } else {
            await interaction.reply({ content: msg, ephemeral: true });
          }
        }
      } catch {}
      console.error(String(e?.message || e));
    }
  });

  await client.login(DISCORD_BOT_TOKEN);

  // Poll loop.
  for (;;) {
    try {
      await pollOnce(client, STATE);
    } catch (e) {
      console.error(String(e?.message || e));
    }
    await new Promise((r) => setTimeout(r, POLL_SECONDS * 1000));
  }
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
