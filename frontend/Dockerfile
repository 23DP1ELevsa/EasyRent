FROM node:22-bookworm-slim AS build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --include=dev --no-audit --no-fund

COPY . .

RUN npm run build

FROM caddy:2-alpine AS runtime

WORKDIR /srv

COPY Caddyfile /etc/caddy/Caddyfile
COPY --from=build /app/dist/ /srv/

# Existing deployments can keep their configured API URL as a build variable.
ARG VITE_API_BASE_URL
ENV API_UPSTREAM=${VITE_API_BASE_URL}

# Railway overrides PORT at runtime; 8080 also supports local Docker runs.
ENV PORT=8080
EXPOSE 8080

CMD ["caddy", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
