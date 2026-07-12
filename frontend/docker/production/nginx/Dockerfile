# Прод-образ фронта (Berry SPA): build-args VITE_* запекаются на сборке (docs/04-devops.md §2.4).
# Build-context — каталог frontend/: docker build -f frontend/docker/production/nginx/Dockerfile frontend
FROM node:24-alpine AS builder

ARG VITE_API_URL
ARG VITE_SMARTCAPTCHA_CLIENT_KEY
ARG VITE_APP_VERSION
ARG VITE_API_MOCKS_ENABLED=false

ENV VITE_API_URL=$VITE_API_URL \
    VITE_SMARTCAPTCHA_CLIENT_KEY=$VITE_SMARTCAPTCHA_CLIENT_KEY \
    VITE_APP_VERSION=$VITE_APP_VERSION \
    VITE_API_MOCKS_ENABLED=$VITE_API_MOCKS_ENABLED

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY . .

RUN npm run build

FROM nginx:1.29-alpine

RUN rm /etc/nginx/conf.d/default.conf

COPY docker/production/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf
COPY --from=builder /app/dist /app/public
