# Static build of the site served by nginx. Used by Coolify (Dockerfile build pack, port 80).
# Build args let one image serve staging (subdomain, noindex) or production.
FROM node:22-alpine AS build
WORKDIR /app
ARG SITE_URL=https://rllogistics.thodalogic.com
ARG NOINDEX=1
ENV NEXT_TELEMETRY_DISABLED=1 \
    SITE_EXPORT=1 \
    NEXT_PUBLIC_SITE_URL=$SITE_URL \
    NEXT_PUBLIC_NOINDEX=$NOINDEX
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npx next build

FROM nginx:1.27-alpine
COPY deploy/nginx-container.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/out /usr/share/nginx/html
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=3s CMD wget -qO- http://127.0.0.1/ >/dev/null || exit 1
