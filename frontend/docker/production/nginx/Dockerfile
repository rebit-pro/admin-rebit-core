# dockerfile
FROM node:20-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm ci || npm i
COPY . .
ARG VITE_API_URL
ARG VITE_GEETEST_CAPTCHA_ID
ARG VITE_APP_VERSION
ENV VITE_API_URL=$VITE_API_URL
ENV VITE_GEETEST_CAPTCHA_ID=$VITE_GEETEST_CAPTCHA_ID
ENV VITE_APP_VERSION=$VITE_APP_VERSION
RUN npm run build

FROM nginx:1.25-alpine
COPY docker/production/nginx/conf.d /etc/nginx/conf.d
WORKDIR /usr/share/nginx/html
COPY --from=build /app/dist .
