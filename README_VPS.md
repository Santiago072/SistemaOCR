# Despliegue en VPS (Hostinger) - Sistema OCR SENA

Guía de comandos para desplegar el sistema en el VPS utilizando Docker y el proxy inverso existente.

---

## 1. Configuración del Dominio / DNS
El registro tipo **A** ya está configurado en Hostinger apuntando a la IP del VPS:
- **Subdominio**: `sistemaocr.slscode.online`
- **IP VPS**: `2.25.154.18` (o la IP principal de tu servidor)
- **Puerto asignado**: `8899`

---

## 2. Clonar el Repositorio en el VPS

Ingresa por SSH a tu VPS y ve al directorio de tus proyectos:

```bash
cd /root/proyectos # O la ruta donde guardas tus contenedores
git clone https://github.com/Santiago072/SistemaOCR.git
cd SistemaOCR
```

---

## 3. Configurar Variables de Entorno

Copia el archivo `.env.example` y genera la clave de Laravel:

```bash
cp .env.example .env
```

Edita `.env` con tus contraseñas seguras si lo deseas:
```bash
nano .env
```

---

## 4. Verificar la Red Docker Externa

Asegúrate de que la red `sodicol_network` exista:

```bash
docker network ls | grep sodicol_network || docker network create sodicol_network
```

---

## 5. Construir y Levantar los Contenedores

Ejecuta Docker Compose para compilar las imágenes e iniciar los 3 servicios:

```bash
docker compose up -d --build
```

Revisa que los 3 contenedores estén en estado **Healthy / Up**:
```bash
docker compose ps
```

---

## 6. Configurar Nginx / Caddy Proxy Inverso del VPS

Para que `https://sistemaocr.slscode.online` redirija las peticiones al contenedor:

### Si tu VPS usa Nginx Proxy Manager o Nginx Host:
Agrega un bloque de servidor apuntando a `http://localhost:8899` o al nombre del contenedor `http://ocr_app:80` en `sodicol_network`:

```nginx
server {
    server_name sistemaocr.slscode.online;

    location / {
        proxy_pass http://127.0.0.1:8899;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        client_max_body_size 50M;
    }
}
```

### Obtener Certificado SSL con Certbot (si aplica):
```bash
certbot --nginx -d sistemaocr.slscode.online
```

---

## 7. Comandos de Mantenimiento

- **Ver logs en tiempo real**:
  ```bash
  docker compose logs -f
  docker compose logs -f ocr_python_service
  docker compose logs -f ocr_app
  ```

- **Reiniciar servicios**:
  ```bash
  docker compose restart
  ```

- **Actualizar cambios del repositorio**:
  ```bash
  git pull origin main
  docker compose up -d --build
  ```
