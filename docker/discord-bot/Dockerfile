FROM node:20-alpine

WORKDIR /app

COPY package.json package-lock.json* ./
# No lockfile in repo yet; use install (omit dev deps) for now.
RUN npm install --omit=dev --no-audit --no-fund

COPY index.js ./

CMD ["node", "index.js"]
