# Docker & Real CA Server — Step by Step Explained
### Everything explained simply with real examples

---

## PART 1 — DOCKER

---

### What is Docker? (Simple Analogy)

Imagine you want to cook a meal. You need a specific kitchen with specific tools.

Without Docker → you go to your own kitchen, but maybe you don't have the right pan, or a different version of an ingredient.

With Docker → you get a **ready-made kitchen in a box**. Open the box, everything is already inside — the exact pan, the exact ingredients, the exact temperature. It works the same every time, on any computer.

In our project:
- The **box** = Docker container
- The **kitchen inside the box** = Linux environment with Node.js installed
- The **meal** = our CA server running
- The **recipe** = Dockerfile

---

### What is a Container?

A container is a **mini isolated computer running inside your computer**.

```
Your Mac (the big computer)
│
├── Your Mac runs normally (macOS)
│
└── Docker Container (mini Linux computer inside)
    ├── Has its own Linux operating system
    ├── Has Node.js installed inside it
    ├── Has our ca-server.js running inside it
    └── Talks to the outside world only through port 8443
```

The container does not know or care what operating system your Mac has. It has everything it needs inside.

---

### File 1 — Dockerfile (the recipe)

**Location:** `real-ca-server/Dockerfile`

This file tells Docker **exactly how to build the container**, step by step. Think of it like a cooking recipe — follow each line in order.

```dockerfile
FROM node:18-alpine
```
**What this means:**
Start with a ready-made Linux computer that already has Node.js 18 installed.
- `node:18-alpine` = a very small Linux (Alpine Linux) with Node.js version 18
- "Alpine" is a tiny Linux — only 5MB — so the container is small and fast
- You don't have to install Node.js yourself — it already comes with this image

Real example: Like saying "start with a kitchen that already has an oven built in."

---

```dockerfile
RUN apk add --no-cache openssl
```
**What this means:**
Install the `openssl` program inside the container.
- `apk` = the package manager for Alpine Linux (like App Store but for Linux)
- `openssl` = a program that creates SSL certificates (the padlock on websites)
- Our ca-server.js runs the openssl command to generate its own HTTPS certificate

Real example: Like saying "also add a specific knife to this kitchen."

Why do we need openssl? Look at this line in ca-server.js:
```javascript
exec('openssl req -x509 -newkey rsa:2048 -keyout server.key -out server.crt ...')
```
The CA server calls openssl to create its own HTTPS certificate when it starts up. Without openssl installed, this would fail.

---

```dockerfile
WORKDIR /app
```
**What this means:**
Create a folder called `/app` inside the container and use it as the working directory.
- Every command from here runs inside `/app`
- It's like saying "open the /app folder and do all work here"

Real example: Like saying "work on the kitchen counter, not the floor."

---

```dockerfile
COPY package*.json ./
```
**What this means:**
Copy `package.json` from your Mac into the `/app` folder inside the container.
- `package.json` lists what Node.js packages the server needs
- We copy this FIRST (before the actual code) for a smart reason explained below

---

```dockerfile
RUN npm install --if-present
```
**What this means:**
Run `npm install` inside the container to install any packages listed in `package.json`.
- `--if-present` = only run if package.json exists (safety check)
- Our ca-server.js uses only built-in Node.js modules, so nothing actually gets installed
- But this step is still good practice — if we add packages later, they'll be installed here

**Smart trick — why copy package.json BEFORE the code?**
Docker saves each step as a "layer." If you change your code but don't change package.json, Docker skips the `npm install` step (uses the saved layer). This makes rebuilding much faster.

---

```dockerfile
COPY ca-server.js ./
```
**What this means:**
Copy our actual CA server code file into the container.
- Now the container has `ca-server.js` at `/app/ca-server.js`

---

```dockerfile
EXPOSE 8443
```
**What this means:**
Tell Docker that this container uses port 8443.
- This is just a label — it documents which port the app uses
- The actual port opening happens in docker-compose.yml (explained below)

Real example: Like writing "this box has a water pipe on the right side" — it tells you where the connection point is.

---

```dockerfile
CMD ["node", "ca-server.js"]
```
**What this means:**
When the container starts, run this command: `node ca-server.js`
- This is what actually starts the CA server
- It's like the ON button

---

### File 2 — docker-compose.yml (the manager)

**Location:** `docker-compose.yml` (at project root)

If the Dockerfile is the recipe for ONE container, then `docker-compose.yml` is the **manager that runs all containers together**.

Here is just the `step-ca` section (our CA server):

```yaml
step-ca:
  build:
    context: ./real-ca-server
    dockerfile: Dockerfile
  container_name: vuproject-step-ca
  ports:
    - "8443:8443"
  networks:
    - vuproject-network
  restart: unless-stopped
```

Let me explain each line:

---

```yaml
step-ca:
```
This is the **name** of this service in docker-compose. Think of it as a label.

---

```yaml
build:
  context: ./real-ca-server
  dockerfile: Dockerfile
```
**What this means:**
Build the container using:
- `context: ./real-ca-server` = look inside the `real-ca-server` folder for files
- `dockerfile: Dockerfile` = use the `Dockerfile` inside that folder as the recipe

---

```yaml
container_name: vuproject-step-ca
```
**What this means:**
Name this container `vuproject-step-ca`.
- This is what you see when you run `docker ps`
- This is also the name you use in commands like `docker logs vuproject-step-ca`

---

```yaml
ports:
  - "8443:8443"
```
**What this means:**
Connect port 8443 of your Mac to port 8443 of the container.

```
Your Mac → port 8443
                ↕  (connected)
Container → port 8443
```

Format is always `"YOUR_MAC_PORT:CONTAINER_PORT"`

Real example: Like a telephone extension.
- The main office number (your Mac's 8443) connects to extension 8443 inside the office (container)
- When you call `localhost:8443`, it goes directly into the container

---

```yaml
networks:
  - vuproject-network
```
**What this means:**
Put this container on a private network called `vuproject-network`.
- Containers on the same network can talk to each other by name
- Other containers can reach this CA server using `step-ca:8443` (using the service name)

---

```yaml
restart: unless-stopped
```
**What this means:**
If the container crashes, automatically restart it.
- `unless-stopped` = restart automatically UNLESS you manually stop it with `docker compose down`

---

### Docker Commands Explained

```bash
docker compose build step-ca
```
Reads the Dockerfile and creates the container image (the "box"). Like preparing the kitchen before cooking.

---

```bash
docker compose up -d step-ca
```
Starts the container in the background.
- `-d` = detached mode (runs in background, doesn't take over your terminal)
- Without `-d`, the container logs would print directly to your terminal

---

```bash
docker ps
```
Shows all running containers. Output looks like:
```
CONTAINER ID   IMAGE                PORTS                    NAMES
521793b327ed   vu-project-step-ca   0.0.0.0:8443->8443/tcp   vuproject-step-ca
```
- `0.0.0.0:8443->8443/tcp` = Mac's port 8443 is connected to container's port 8443
- `vuproject-step-ca` = the container name

---

```bash
docker logs vuproject-step-ca
```
Shows what the CA server printed when it started. Should look like:
```
Initialized with 4 default certificates
🚀 VuProject CA Server running on https://localhost:8443
```

---

```bash
docker compose down
```
Stops and removes the container. The image still exists but the container stops.

---

```bash
docker compose restart step-ca
```
Stops and starts the container again. Useful when you want a fresh CA server (clears all generated certificates from memory).

---

---

## PART 2 — REAL CA SERVER (ca-server.js)

---

### What is a CA Server? (Simple Analogy)

In the real world, when a website wants an SSL certificate (the padlock), they go to a trusted company like **Let's Encrypt** or **DigiCert** and say "please issue me a certificate for mywebsite.com." That company is called a **Certificate Authority (CA)**.

We cannot use Let's Encrypt for this project. So we **built our own CA server** — a private one just for this project.

Think of it like this:
- Let's Encrypt = Government passport office (trusted by everyone)
- Our CA Server = University's internal ID card office (trusted within our system)

Our CA server does the same things Let's Encrypt does:
- Issues (creates) new certificates
- Lists all certificates it has issued
- Revokes (cancels) certificates
- Renews certificates that are expiring

---

### The Full Logic of ca-server.js (Line by Line)

---

#### Step 1 — Imports (Tools We Need)

```javascript
const https = require('https');  // Built-in module to create HTTPS server
const fs = require('fs');        // Built-in module to read/write files
const crypto = require('crypto');// Built-in module for random numbers & hashing
const { exec } = require('child_process'); // Built-in module to run shell commands
```

These are all **built-in** Node.js modules — no npm install needed.
- `https` = creates a server that uses HTTPS (secure, like a website with padlock)
- `fs` = reads files from disk (used to read the SSL certificate files)
- `crypto` = generates random IDs, fingerprints, serial numbers
- `exec` = runs a terminal command (used to run `openssl`)

---

#### Step 2 — The Certificate Store (Memory)

```javascript
const certificateStore = [];
```

This is a simple JavaScript array that holds all certificates while the server is running.

Think of it like a **whiteboard in an office**:
- New certificate issued → write it on the whiteboard
- Someone asks for all certs → read from the whiteboard
- Server restarts → whiteboard is erased (back to empty)

This is called **in-memory storage**. It's fast but NOT permanent.

**This is why we also save to MySQL database** — because when Docker restarts, the whiteboard gets erased.

---

#### Step 3 — The generateCertificate() Function

```javascript
function generateCertificate(commonName, sans = [], status = 'Valid') {
```

This function takes 3 inputs:
- `commonName` = the website domain, e.g. `"google.com"`
- `sans` = extra domains, e.g. `["www.google.com", "mail.google.com"]`
- `status` = `'Valid'` by default

**Inside the function:**

```javascript
const now = new Date();
const validFrom = new Date(now.getTime() - Math.random() * 30 * 24 * 60 * 60 * 1000);
const validTo   = new Date(now.getTime() + (Math.random() * 365 + 30) * 24 * 60 * 60 * 1000);
```
- `validFrom` = a random date in the past 30 days (when the cert "started")
- `validTo` = a random date 30 to 395 days from now (when it expires)
- `Math.random() * 30` = a random number between 0 and 30 (days)
- `* 24 * 60 * 60 * 1000` = converts days into milliseconds (how JavaScript measures time)

---

```javascript
const issuers = [
    'VuProject Real CA',
    'VuProject Enterprise CA',
    'VuProject Secure CA',
    ...
];
```
A list of 8 different issuer names. The function randomly picks one for each certificate. This makes certificates look varied and realistic.

---

```javascript
return {
    id: `cert_${Date.now()}_${crypto.randomBytes(4).toString('hex')}`,
```
Creates a unique ID for every certificate.
- `Date.now()` = current time in milliseconds (e.g. `1782587845573`)
- `crypto.randomBytes(4).toString('hex')` = 4 random bytes as hex (e.g. `aee2dd2e`)
- Result: `cert_1782587845573_aee2dd2e` — guaranteed unique every time

---

```javascript
    serialNumber: crypto.randomBytes(6).toString('hex').toUpperCase(),
```
Generates a random serial number like `5FDBA747DF6E`.
- Real CA serial numbers are just big unique numbers
- `crypto.randomBytes(6)` = 6 random bytes = 12 hex characters

---

```javascript
    fingerprint: `SHA256:${crypto.randomBytes(16).toString('hex').toUpperCase()}`
```
A fingerprint is like a certificate's "face ID" — a unique hash.
- Real fingerprints are SHA256 hashes of the certificate content
- We simulate this with 16 random bytes (32 hex characters)
- Example: `SHA256:692C29E876643B7D35688F9406DBEA2A`

---

```javascript
    keyUsage: ['Digital Signature', 'Key Encipherment'],
    extendedKeyUsage: ['TLS Web Server Authentication'],
    source: 'real-ca-server',
    realTime: true,
```
- `keyUsage` = what this certificate is allowed to do (sign things, encrypt things)
- `extendedKeyUsage` = what this cert is for specifically (TLS = HTTPS websites)
- `source: 'real-ca-server'` = tells the dashboard this cert came from Docker, not the database
- `realTime: true` = another marker so the dashboard knows this is a live cert

---

#### Step 4 — initializeCertificates() (Default Certs on Startup)

```javascript
function initializeCertificates() {
    if (certificateStore.length === 0) {
        const hosts = [
            { name: '127.0.0.1',      sans: ['127.0.0.1', 'localhost'] },
            { name: 'localhost',       sans: ['localhost', '127.0.0.1'] },
            { name: 'wacman.com',      sans: ['wacman.com', 'www.wacman.com'] },
            { name: '192.168.3.92',   sans: ['192.168.3.92'] }
        ];
        
        hosts.forEach(host => {
            certificateStore.push(generateCertificate(host.name, host.sans));
        });
    }
}

initializeCertificates(); // runs immediately when server starts
```

**What this does:**
Every time the CA server starts (including when Docker starts), it automatically creates 4 certificates for common local addresses.

Why these 4?
- `127.0.0.1` and `localhost` = your own computer's address
- `wacman.com` = a demo domain for testing
- `192.168.3.92` = a local network IP address

Without this, the dashboard would show 0 certificates when you first open it. With this, there are always 4 to show.

**Important:** `if (certificateStore.length === 0)` means it only runs once. If you somehow call it again, it won't add duplicates.

---

#### Step 5 — The Routes (4 API Endpoints)

The server has 4 things it can respond to:

```javascript
const routes = {
    '/health':                  ...,  // Check if server is alive
    '/certificates':            ...,  // Get all certificates
    '/certificates/generate':   ...,  // Create a new certificate
    '/certificates/revoke':     ...,  // Cancel a certificate
    '/certificates/renew':      ...,  // Renew a certificate
};
```

---

**Route 1: GET /health**

```javascript
'/health': (req, res) => {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({
        status: 'ok',
        name: 'VuProject CA',
        version: '1.0.0',
        timestamp: new Date().toISOString()
    }));
},
```

This simply replies "I am alive." Laravel calls this to check if the CA server (Docker) is running.

Test it yourself:
```bash
curl -k https://localhost:8443/health
```
Response:
```json
{ "status": "ok", "name": "VuProject CA", "version": "1.0.0" }
```

---

**Route 2: GET /certificates**

```javascript
'/certificates': (req, res) => {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({
        success: true,
        data: certificateStore,       // returns the entire array
        total: certificateStore.length,
        source: 'real-ca-server',
        real_time: true
    }));
},
```

Returns everything in `certificateStore` (the memory whiteboard).

Test it:
```bash
curl -k https://localhost:8443/certificates
```
When Docker just started: returns 4 default certificates.
After you generate more: returns 4 + however many you generated.

---

**Route 3: POST /certificates/generate**

This is the most important route. Here is the full logic:

```javascript
'/certificates/generate': (req, res) => {
    let body = '';
    
    req.on('data', chunk => { body += chunk.toString(); });
    // ↑ collects the request body piece by piece as it arrives
    
    req.on('end', () => {
        // ↑ fires when all data has arrived
        
        const data = JSON.parse(body);
        // ↑ converts the JSON string into a JavaScript object
        
        const commonName = data.commonName || 'generated.example.com';
        // ↑ uses what was sent, or a default if nothing was sent
        
        const subjectAltNames = data.subjectAltNames || [commonName, `www.${commonName}`];
        // ↑ uses sent SANs, or auto-creates ["domain.com", "www.domain.com"]
        
        const cert = generateCertificate(commonName, subjectAltNames, 'Valid');
        // ↑ calls the function we explained above — creates the certificate object
        
        certificateStore.push(cert);
        // ↑ adds it to the memory array — NOW it will appear in GET /certificates
        
        res.end(JSON.stringify({
            success: true,
            id: cert.id,
            certificate: cert,
            total_certificates: certificateStore.length
        }));
    });
},
```

Real example — you send this:
```json
{ "commonName": "myshop.com", "validityDays": 365 }
```

The server creates and returns something like:
```json
{
  "success": true,
  "id": "cert_1782587845573_aee2dd2e",
  "certificate": {
    "commonName": "myshop.com",
    "status": "Valid",
    "validFrom": "2026-05-30T...",
    "validTo": "2027-03-15T...",
    "issuer": "VuProject Secure CA",
    "serialNumber": "5FDBA747DF6E",
    "subjectAltNames": ["myshop.com", "www.myshop.com"],
    "source": "real-ca-server"
  }
}
```

---

**Route 4: POST /certificates/revoke**

```javascript
const certIndex = certificateStore.findIndex(cert => 
    cert.id === certificateId || cert.serialNumber === certificateId
);
// ↑ searches the array for a certificate matching the ID

if (certIndex === -1) {
    // not found — return 404 error
}

certificateStore[certIndex].status = 'Revoked';
certificateStore[certIndex].revokedAt = new Date().toISOString();
// ↑ does NOT delete it — just changes the status to 'Revoked'
// ↑ also records WHEN it was revoked
```

Real example: Like cancelling a library card. The card still exists in the system but is marked as "cancelled." You can still see it, but it won't work.

---

**Route 5: POST /certificates/renew**

```javascript
const oldCert = certificateStore.find(cert => cert.id === certificateId);
// ↑ finds the old certificate

const newCert = generateCertificate(oldCert.commonName, oldCert.subjectAltNames, 'Valid');
// ↑ creates a BRAND NEW certificate for the same domain

certificateStore.push(newCert);
// ↑ adds the new certificate to the store

oldCert.status = 'Renewed';
oldCert.renewedTo = newCert.id;
// ↑ marks the OLD certificate as 'Renewed' and links it to the new one
```

Real example: Like renewing your passport. The old passport becomes invalid, a new one is issued for the same person with a new expiry date.

---

#### Step 6 — startServer() (Starting HTTPS)

```javascript
function startServer() {
    exec('openssl req -x509 -newkey rsa:2048 -keyout server.key -out server.crt -days 365 -nodes -subj "/CN=localhost/O=VuProject CA"', (error) => {
```

This runs the `openssl` command to **create the CA server's own HTTPS certificate**.

Breaking down the openssl command:
| Part | Meaning |
|------|---------|
| `req -x509` | Create a self-signed certificate |
| `-newkey rsa:2048` | Generate a new RSA key, 2048 bits long |
| `-keyout server.key` | Save the private key to `server.key` |
| `-out server.crt` | Save the certificate to `server.crt` |
| `-days 365` | Valid for 1 year |
| `-nodes` | No password on the key (so the server can start without asking) |
| `-subj "/CN=localhost"` | The certificate is for `localhost` |

After the command finishes, the server reads those files:
```javascript
const options = {
    key: fs.readFileSync('server.key'),   // the private key
    cert: fs.readFileSync('server.crt')   // the certificate
};

const server = https.createServer(options, ...);
```

This is what makes the CA server itself use HTTPS. So when Laravel calls `https://localhost:8443`, the connection is encrypted.

---

```javascript
res.setHeader('Access-Control-Allow-Origin', '*');
res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
```

These are **CORS headers**. They tell browsers "any website is allowed to call this server."
- Without these, browsers would block the requests for security
- We use `*` (allow everyone) because this is a private internal server

---

```javascript
const url = req.url.split('?')[0];

if (routes[url]) {
    routes[url](req, res);
} else {
    res.writeHead(404);
    res.end(JSON.stringify({ error: 'Not found' }));
}
```

This is the **router** — decides which function handles each request.
- `req.url.split('?')[0]` = removes query parameters (e.g. `/certificates?page=1` becomes `/certificates`)
- Looks up the URL in the `routes` object
- If found → calls that function
- If not found → returns 404 error

---

---

## PART 3 — HOW DOCKER AND CA SERVER WORK TOGETHER

---

### The Full Startup Sequence

When you run `docker compose up -d step-ca`, here is exactly what happens step by step:

```
Step 1: Docker reads docker-compose.yml
        → Finds "step-ca" service
        → Build context = ./real-ca-server

Step 2: Docker reads real-ca-server/Dockerfile
        → "Start with node:18-alpine Linux image"
        → "Install openssl"
        → "Copy ca-server.js into /app"

Step 3: Docker creates the container
        → A mini Linux computer starts up
        → Port 8443 inside container is connected to port 8443 on your Mac

Step 4: Docker runs CMD ["node", "ca-server.js"]
        → Node.js starts running ca-server.js
        → ca-server.js calls openssl to generate server.key and server.crt
        → initializeCertificates() runs → 4 default certs added to memory
        → HTTPS server starts listening on port 8443
        → Prints: "🚀 VuProject CA Server running on https://localhost:8443"

Step 5: Container is now running
        → You can see it with: docker ps
        → You can check it: curl -k https://localhost:8443/health
```

---

### The Full Flow When You Generate a Certificate

When you click "Generate Certificate" on the dashboard, this is what happens across ALL systems:

```
[1] You click button on React (localhost:3000)
          ↓
[2] React sends: POST http://localhost:8000/api/certificates/generate
                 Body: { "commonName": "myshop.com", "validityDays": 365 }
          ↓
[3] Laravel (api.php) receives the request
    Laravel first checks: Is Docker CA server online?
          ↓
[4] Laravel sends: POST https://localhost:8443/certificates/generate
    (This goes into the Docker container)
          ↓
[5] ca-server.js inside Docker receives the request
    Calls generateCertificate("myshop.com", ...)
    Pushes new cert into certificateStore[]
    Returns the certificate JSON
          ↓
[6] Laravel receives the certificate from Docker
    Saves it to MySQL database (so it persists even if Docker restarts)
    Returns success response to React
          ↓
[7] React refreshes the dashboard
    Calls: GET http://localhost:8000/api/live-certificates
          ↓
[8] Laravel calls: GET https://localhost:8443/certificates (Docker)
    Docker returns all certs including the new myshop.com one
          ↓
[9] Dashboard shows the new certificate
    Chip shows: "🔴 LIVE – Real CA Server"
```

---

### The Smart Fallback — CA Online vs Offline

This logic is in `vu-laravel/routes/api.php`:

```php
// GET /api/live-certificates
try {
    // Try to reach Docker container
    $response = $client->get('https://localhost:8443/certificates');
    // Docker is running → return CA certificates
    return response()->json([
        'ca_server_active' => true,   // tells dashboard: CA is online
        'data' => $data['data'],      // real CA certificates
    ]);
} catch (\Exception $e) {
    // Docker is stopped → return database certificates
    $dbCertificates = Certificate::all();
    return response()->json([
        'ca_server_active' => false,  // tells dashboard: CA is offline
        'data' => $certificates,      // database certificates
    ]);
}
```

**What the dashboard does with `ca_server_active`:**

```typescript
// In Dashboard.tsx
const isCA = data.ca_server_active === true;
setCaOnline(isCA);

// The chip in the top right shows:
label={caOnline ? '🔴 LIVE – Real CA Server' : '💾 Database'}
```

Real example:
- **Docker running** → `ca_server_active: true` → chip shows "LIVE" → certs from Docker memory
- **Docker stopped** → `ca_server_active: false` → chip shows "Database" → certs from MySQL

This means the dashboard **never breaks**. Even if Docker goes down, users still see their certificates.

---

### Why We Save to MySQL Even When Docker is Running

The Docker container uses **in-memory storage** (a JavaScript array `[]`).

```
Docker starts  → certificateStore = [cert1, cert2, cert3, cert4]
You generate   → certificateStore = [cert1, cert2, cert3, cert4, myshop.com]
Docker stops   → certificateStore is GONE (array deleted)
Docker starts  → certificateStore = [cert1, cert2, cert3, cert4]  (back to 4)
myshop.com is LOST
```

That's why Laravel always saves to MySQL:
```
Docker starts  → MySQL: [cert1, cert2, cert3, cert4, myshop.com]  (still there!)
Docker stops   → Dashboard switches to MySQL → myshop.com still visible
Docker starts  → Dashboard switches back to Docker (4 default certs show)
```

The MySQL database is the permanent backup. Docker is the live view.

---

### Summary Table

| Question | Answer |
|----------|--------|
| What is Docker here? | A mini Linux computer inside your Mac |
| What runs inside Docker? | Our Node.js CA server (ca-server.js) |
| What port does it use? | 8443 (HTTPS) |
| How does Laravel reach Docker? | `https://localhost:8443` (port 8443 is mapped from Mac to container) |
| Where does Docker store certificates? | In a JavaScript array in memory (lost on restart) |
| Where does MySQL store certificates? | In the `certificates` table (permanent) |
| What happens when Docker stops? | Dashboard automatically shows MySQL certificates instead |
| What file builds the container? | `real-ca-server/Dockerfile` |
| What file manages the container? | `docker-compose.yml` |
| What is the CA server's job? | Issue, list, revoke, and renew certificates |
| Why HTTPS on port 8443? | Because SSL certificates must be served over a secure connection |
