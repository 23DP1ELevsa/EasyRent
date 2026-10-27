FROM node:22-bookworm-slim AS build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --include=dev --no-audit --no-fund

COPY . .

# Railway passes service variables declared as build arguments to this stage.
# Vite embeds this public API URL in the compiled frontend.
ARG VITE_API_BASE_URL=https://easyrent-production-04d5.up.railway.app
ENV VITE_API_BASE_URL=${VITE_API_BASE_URL}
RUN npm run build

FROM caddy:2-alpine AS runtime

WORKDIR /srv

COPY Caddyfile /etc/caddy/Caddyfile
COPY --from=build /app/dist/ /srv/

# Railway overrides PORT at runtime; 8080 also supports local Docker runs.
ENV PORT=8080
EXPOSE 8080

CMD ["caddy", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
