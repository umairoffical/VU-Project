#!/bin/bash

# VuProject SSL/TLS Setup Script
# This script generates self-signed certificates for development

set -e

echo "🔐 VuProject SSL/TLS Setup"
echo "=========================="
echo ""

# Create SSL directory
SSL_DIR="./docker/nginx/ssl"
mkdir -p "$SSL_DIR"

echo "📁 Creating SSL directory: $SSL_DIR"
echo ""

# Generate Diffie-Hellman parameters
echo "⚙️  Generating Diffie-Hellman parameters (this may take a while)..."
openssl dhparam -out "$SSL_DIR/dhparam.pem" 2048

# Generate private key
echo "🔑 Generating private key..."
openssl genrsa -out "$SSL_DIR/key.pem" 4096

# Generate certificate signing request
echo "📝 Generating certificate signing request..."
openssl req -new -key "$SSL_DIR/key.pem" -out "$SSL_DIR/csr.pem" \
    -subj "/C=US/ST=State/L=City/O=VuProject/OU=Development/CN=localhost"

# Generate self-signed certificate
echo "📜 Generating self-signed certificate..."
openssl x509 -req -days 365 -in "$SSL_DIR/csr.pem" -signkey "$SSL_DIR/key.pem" \
    -out "$SSL_DIR/cert.pem" \
    -extfile <(printf "subjectAltName=DNS:localhost,DNS:*.local,IP:127.0.0.1")

# Create certificate chain
echo "🔗 Creating certificate chain..."
cp "$SSL_DIR/cert.pem" "$SSL_DIR/chain.pem"

# Set permissions
chmod 644 "$SSL_DIR/cert.pem"
chmod 600 "$SSL_DIR/key.pem"
chmod 644 "$SSL_DIR/dhparam.pem"

echo ""
echo "✅ SSL certificates generated successfully!"
echo ""
echo "📋 Files created:"
echo "  - $SSL_DIR/key.pem (Private Key)"
echo "  - $SSL_DIR/cert.pem (Certificate)"
echo "  - $SSL_DIR/chain.pem (Certificate Chain)"
echo "  - $SSL_DIR/dhparam.pem (DH Parameters)"
echo ""
echo "⚠️  Note: These are self-signed certificates for development only."
echo "   For production, use certificates from a trusted CA (Let's Encrypt, etc.)"
echo ""
echo "🚀 You can now start the services with HTTPS enabled!"
echo ""

