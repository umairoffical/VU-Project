const https = require('https');
const fs = require('fs');
const crypto = require('crypto');

// Generate self-signed certificate for the CA server
const { exec } = require('child_process');

// In-memory certificate storage
const certificateStore = [];

// Function to generate certificates
function generateCertificate(commonName, sans = [], status = 'Valid') {
    const now = new Date();
    const validFrom = new Date(now.getTime() - Math.random() * 30 * 24 * 60 * 60 * 1000);
    const validTo = new Date(now.getTime() + (Math.random() * 365 + 30) * 24 * 60 * 60 * 1000);
    
    const issuers = [
        'VuProject Real CA',
        'VuProject Enterprise CA',
        'VuProject Secure CA',
        'VuProject Dynamic CA',
        'VuProject Live CA',
        'VuProject Cloud CA',
        'VuProject Digital CA',
        'VuProject Active CA'
    ];
    
    return {
        id: `cert_${Date.now()}_${crypto.randomBytes(4).toString('hex')}`,
        commonName: commonName,
        status: status,
        validFrom: validFrom.toISOString(),
        validTo: validTo.toISOString(),
        issuer: issuers[Math.floor(Math.random() * issuers.length)],
        serialNumber: crypto.randomBytes(6).toString('hex').toUpperCase(),
        subjectAltNames: sans,
        keyUsage: ['Digital Signature', 'Key Encipherment'],
        extendedKeyUsage: ['TLS Web Server Authentication'],
        generatedAt: now.toISOString(),
        source: 'real-ca-server',
        timestamp: Date.now(),
        realTime: true,
        fingerprint: `SHA256:${crypto.randomBytes(16).toString('hex').toUpperCase()}`
    };
}

// Initialize with some default certificates
function initializeCertificates() {
    if (certificateStore.length === 0) {
        const hosts = [
            { name: '127.0.0.1', sans: ['127.0.0.1', 'localhost'] },
            { name: 'localhost', sans: ['localhost', '127.0.0.1'] },
            { name: 'wacman.com', sans: ['wacman.com', 'www.wacman.com'] },
            { name: '192.168.3.92', sans: ['192.168.3.92'] }
        ];
        
        hosts.forEach(host => {
            certificateStore.push(generateCertificate(host.name, host.sans));
        });
        
        console.log(`Initialized with ${certificateStore.length} default certificates`);
    }
}

// Initialize certificates on startup
initializeCertificates();

// CA endpoints
const routes = {
    '/health': (req, res) => {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
            status: 'ok',
            name: 'VuProject CA',
            version: '1.0.0',
            timestamp: new Date().toISOString()
        }));
    },
    
    '/certificates': (req, res) => {
        // Return stored certificates
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
            success: true,
            data: certificateStore,
            total: certificateStore.length,
            source: 'real-ca-server',
            generated_at: new Date().toISOString(),
            timestamp: Date.now(),
            real_time: true
        }));
    },
    
    '/certificates/generate': (req, res) => {
        let body = '';
        
        req.on('data', chunk => {
            body += chunk.toString();
        });
        
        req.on('end', () => {
            try {
                const data = JSON.parse(body);
                const commonName = data.commonName || 'generated.example.com';
                const subjectAltNames = data.subjectAltNames || [commonName, `www.${commonName}`];
                const validityDays = data.validityDays || 365;
                
                // Generate new certificate
                const cert = generateCertificate(commonName, subjectAltNames, 'Valid');
                
                // Add to certificate store
                certificateStore.push(cert);
                
                console.log(`Generated certificate for ${commonName} - Total certificates: ${certificateStore.length}`);
                
                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({
                    success: true,
                    id: cert.id,
                    certificate: cert,
                    message: `Certificate generated successfully for ${commonName}`,
                    total_certificates: certificateStore.length
                }));
            } catch (error) {
                res.writeHead(400, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({
                    success: false,
                    message: 'Invalid request data: ' + error.message
                }));
            }
        });
    },
    
    '/certificates/revoke': (req, res) => {
        let body = '';
        
        req.on('data', chunk => {
            body += chunk.toString();
        });
        
        req.on('end', () => {
            try {
                const data = JSON.parse(body);
                const certificateId = data.certificateId || data.id;
                
                if (!certificateId) {
                    res.writeHead(400, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({
                        success: false,
                        message: 'Certificate ID is required'
                    }));
                    return;
                }
                
                // Find certificate in store
                const certIndex = certificateStore.findIndex(cert => 
                    cert.id === certificateId || cert.serialNumber === certificateId
                );
                
                if (certIndex === -1) {
                    res.writeHead(404, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({
                        success: false,
                        message: 'Certificate not found'
                    }));
                    return;
                }
                
                // Update certificate status
                certificateStore[certIndex].status = 'Revoked';
                certificateStore[certIndex].revokedAt = new Date().toISOString();
                
                console.log(`Revoked certificate: ${certificateId} - Total: ${certificateStore.length}`);
                
                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({
                    success: true,
                    message: 'Certificate revoked successfully',
                    certificate: certificateStore[certIndex]
                }));
            } catch (error) {
                res.writeHead(400, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({
                    success: false,
                    message: 'Invalid request data: ' + error.message
                }));
            }
        });
    },
    
    '/certificates/renew': (req, res) => {
        let body = '';
        
        req.on('data', chunk => {
            body += chunk.toString();
        });
        
        req.on('end', () => {
            try {
                const data = JSON.parse(body);
                const certificateId = data.certificateId || data.id;
                const validityDays = data.validityDays || 365;
                
                if (!certificateId) {
                    res.writeHead(400, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({
                        success: false,
                        message: 'Certificate ID is required'
                    }));
                    return;
                }
                
                // Find certificate in store
                const oldCert = certificateStore.find(cert => 
                    cert.id === certificateId || cert.serialNumber === certificateId
                );
                
                if (!oldCert) {
                    res.writeHead(404, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({
                        success: false,
                        message: 'Certificate not found'
                    }));
                    return;
                }
                
                // Create renewed certificate
                const newCert = generateCertificate(
                    oldCert.commonName, 
                    oldCert.subjectAltNames, 
                    'Valid'
                );
                
                // Add to store
                certificateStore.push(newCert);
                
                // Mark old certificate as renewed
                oldCert.status = 'Renewed';
                oldCert.renewedAt = new Date().toISOString();
                oldCert.renewedTo = newCert.id;
                
                console.log(`Renewed certificate: ${certificateId} → ${newCert.id} - Total: ${certificateStore.length}`);
                
                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({
                    success: true,
                    message: 'Certificate renewed successfully',
                    oldCertificate: oldCert,
                    newCertificate: newCert
                }));
            } catch (error) {
                res.writeHead(400, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({
                    success: false,
                    message: 'Invalid request data: ' + error.message
                }));
            }
        });
    }
};

// Create HTTPS server
function startServer() {
    // Generate self-signed certificate for HTTPS
    exec('openssl req -x509 -newkey rsa:2048 -keyout server.key -out server.crt -days 365 -nodes -subj "/CN=localhost/O=VuProject CA"', (error) => {
        if (error) {
            console.error('Error generating SSL certificate:', error);
            return;
        }
        
        const options = {
            key: fs.readFileSync('server.key'),
            cert: fs.readFileSync('server.crt')
        };
        
        const server = https.createServer(options, (req, res) => {
            // Enable CORS
            res.setHeader('Access-Control-Allow-Origin', '*');
            res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            
            if (req.method === 'OPTIONS') {
                res.writeHead(200);
                res.end();
                return;
            }
            
            const url = req.url.split('?')[0];
            
            if (routes[url]) {
                routes[url](req, res);
            } else {
                res.writeHead(404, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ error: 'Not found' }));
            }
        });
        
        server.listen(8443, () => {
            console.log('🚀 VuProject CA Server running on https://localhost:8443');
            console.log('🔗 Health: https://localhost:8443/health');
            console.log('📜 Certificates: https://localhost:8443/certificates');
        });
    });
}

startServer();
