FROM node:18-alpine

RUN apk add --no-cache openssl

WORKDIR /app

COPY package*.json ./
RUN npm install --if-present

COPY ca-server.js ./

EXPOSE 8443

CMD ["node", "ca-server.js"]
