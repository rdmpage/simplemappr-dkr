# Deploying SimpleMappr to Hetzner Cloud

This guide covers deploying SimpleMappr to a Hetzner Cloud VPS. The same general approach works for other cloud providers (DigitalOcean, Linode, AWS EC2, etc.).

## Requirements

- Hetzner Cloud account
- SSH key pair
- Domain name (optional, for HTTPS)

## Server Specifications

Recommended minimum:
- **CPU:** 2 vCPU
- **RAM:** 4 GB
- **Storage:** 40 GB SSD
- **OS:** Ubuntu 24.04 LTS

Hetzner CX22 or CX32 instance types work well.

## Step 1: Create the Server

1. Log into [Hetzner Cloud Console](https://console.hetzner.cloud)
2. Create a new project (or use existing)
3. Click **Add Server**
4. Configure:
   - **Location:** Choose based on your audience
   - **Image:** Ubuntu 24.04
   - **Type:** CX22 (2 vCPU, 4 GB RAM, 40 GB SSD)
   - **Networking:** Public IPv4 (IPv6 is free)
   - **SSH Keys:** Add your public key
   - **Name:** e.g., `simplemappr`
5. Click **Create & Buy Now**

Note the server's IP address once created.

## Step 2: Initial Server Setup

SSH into your server:

```bash
ssh root@YOUR_SERVER_IP
```

Update the system and install Docker:

```bash
# Update system packages
apt update && apt upgrade -y

# Install Docker and required tools
curl -fsSL https://get.docker.com | sh
apt install docker-compose-plugin unzip curl -y

# Verify installation
docker --version
docker compose version
```

## Step 3: Clone and Configure

```bash
# Clone the repository
git clone https://github.com/YOUR_USERNAME/simplemappr-dkr.git
cd simplemappr-dkr

# Create environment file
cp .env.example .env

# Edit if needed (defaults should work)
nano .env
```

## Step 4: Download Map Data

### Natural Earth Data (required)

```bash
mkdir -p mapserver/maps
bash scripts/download-naturalearth.sh ./mapserver/maps
```

### Optional proprietary map layers

Run the external data script, which automatically downloads what it can
and prints instructions for anything requiring manual download:

```bash
bash scripts/download-external-data.sh ./mapserver/maps
```

This will:
- **Auto-download** the Conservation International Biodiversity Hotspots from Zenodo (CC-BY-SA 4.0)
- **Print instructions** for the two WWF datasets (terrestrial and marine ecoregions), which require visiting the WWF website to agree to their terms before downloading

Without these layers the app works fine — they simply won't appear in the editor.

## Step 5: Launch Services

```bash
# Build and start all services
docker compose up -d

# Watch the logs (Ctrl+C to exit)
docker compose logs -f
```

The first startup will take several minutes as it:
- Builds the Docker images
- Initializes the database

## Step 6: Verify Installation

Once the logs show the services are ready:

```bash
# Check service status
docker compose ps

# Test the health endpoint
curl http://localhost/health
```

Access the application at `http://YOUR_SERVER_IP/`

## Step 7: Configure Firewall (Recommended)

```bash
# Install UFW if not present
apt install ufw -y

# Allow SSH and HTTP/HTTPS
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp

# Enable firewall
ufw enable
```

## Adding HTTPS with a Domain (Optional)

If you have a domain name:

### 1. Configure DNS

Add an A record pointing to your server IP:
```
Type: A
Name: simplemappr (or @ for root)
Value: YOUR_SERVER_IP
TTL: 300
```

### 2. Install Caddy as Reverse Proxy

Caddy automatically obtains and renews SSL certificates.

```bash
# Install Caddy
apt install -y debian-keyring debian-archive-keyring apt-transport-https
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
apt update
apt install caddy -y
```

### 3. Configure Caddy

Edit `/etc/caddy/Caddyfile`:

```
simplemappr.yourdomain.com {
    reverse_proxy localhost:80
}
```

Restart Caddy:

```bash
systemctl restart caddy
```

Caddy will automatically obtain an SSL certificate from Let's Encrypt.

## Managing the Application

### View logs
```bash
docker compose logs -f
docker compose logs -f app      # Specific service
docker compose logs -f render   # Render service
```

### Restart services
```bash
docker compose restart
```

### Stop services
```bash
docker compose down
```

### Update to latest version
```bash
git pull origin main

# Rebuild render container (re-downloads any new shapefiles)
docker compose build --no-cache render
docker compose up -d

# Regenerate projection thumbnails
bash scripts/generate-projection-thumbnails.sh
```

### View resource usage
```bash
docker stats
```

## Troubleshooting

### Services won't start
Check logs for errors:
```bash
docker compose logs --tail=100
```

### Maps not rendering
Verify the render service is healthy:
```bash
curl http://localhost:8080/health
```

Check if Natural Earth data downloaded:
```bash
docker compose exec render ls -la /mapserver/data/
```

### Database connection issues
Check database logs:
```bash
docker compose logs db
```

### Out of disk space
Check disk usage:
```bash
df -h
docker system df
```

Clean up unused Docker resources:
```bash
docker system prune -a
```

## Backup

### Database backup
```bash
docker compose exec db pg_dump -U simplemappr simplemappr > backup.sql
```

### Restore database
```bash
cat backup.sql | docker compose exec -T db psql -U simplemappr simplemappr
```

## Security Considerations

1. **Change default database password** in `.env` before first run
2. **Keep system updated:** `apt update && apt upgrade`
3. **Use HTTPS** in production (see Caddy setup above)
4. **Consider fail2ban** for SSH protection:
   ```bash
   apt install fail2ban -y
   systemctl enable fail2ban
   ```

## Cost Estimate

Hetzner Cloud CX22:
- ~€4.35/month (or ~$5/month)
- Includes 20 TB traffic

This is sufficient for moderate usage. Scale up to CX32 or CX42 if needed.
