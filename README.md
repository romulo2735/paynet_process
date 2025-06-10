# 🧠 Paynet Process

Microserviço Laravel para **validação, processamento e enriquecimento de dados cadastrais de usuários**, com **integrações externas**, **cache Redis**, **filas com Horizon**, e documentação OpenAPI (Swagger).

---

## 📦 Tecnologias Utilizadas

- Laravel 11+
- PHP 8.3
- Redis (cache e filas)
- MySQL (persistência)
- Horizon (monitoramento de filas)
- Docker / Docker Compose
- Swagger (OpenAPI)
- PHPUnit (testes com cobertura)

---

## 🚀 Setup Rápido

```bash
git clone https://github.com/seuusuario/paynet_process.git
cd paynet_process

cp .env.example .env
docker-compose up -d --build

# Instalar dependências
docker exec -it paynet_process_app composer install

# Gerar chave da aplicação
docker exec -it paynet_process_app php artisan key:generate

# Rodar migrations
docker exec -it paynet_process_app php artisan migrate

# Gerar documentação Swagger
docker exec -it paynet_process_app php artisan l5-swagger:generate
```

## 📬 Endpoints

### POST /api/v1/users/process

Processa e envia dados do usuário para a fila.

**Payload**

```json
{
    "cpf": "12345678901",
    "cep": "12345678",
    "email": "mFt7t@example.com"
}
```
 - Validação via Request
 - Envia dados para a fila
 - Em caso de erro: HTTP 422 com mensagens estruturadas
 - Retorna 202 Accepted ou 200 com status cached

---

## ⚙️ Jobs e Filas

🔄 ProcessUserJob

Executa o processamento assíncrono:

 - Busca paralela nas 3 APIs externas
 - Retry automático (até 3x em falhas)
 - Log estruturado dos fluxos (start, error, retries, hits/misses)
 - Armazena no banco e no Redis

---

## 🧪 Testes
- Rodar testes:

```bash
docker exec -it paynet_process_app php artisan test
```

- Cobertura mínima: 80%
- Testes de:
- DTOs
- Services
- Jobs
- Requisições Feature
- Cache e Redis

---

## 🔍 Monitoramento com Horizon

Horizon está disponível em:
```bash
http://localhost:8080/horizon/dashboard
```

___

## 🧱 Docker

Serviços
- `app`: Laravel + PHP + Horizon
- `mysql`: MySQL 8
- `redis`: Redis para cache/filas

Comandos:
```bash
docker-compose up -d --build
docker-compose down
docker exec -it paynet_process_app bash
```
___

## 📚 Documentação Swagger

Acesse em:
```bash
http://localhost:8080/api/documentation
```

Ou gere via:
```bash
php artisan l5-swagger:generate
```

## Postman

[Paynet Process - CASE TEST.postman_collection.json](Paynet%20Process%20-%20CASE%20TEST.postman_collection.json)
___

## 📁 Estrutura de Pastas

```bash
app/
  ├── DTOs/                 # Objetos de transferência de dados
  ├── Integrations/         # Integração com APIs externas
  ├── Jobs/                 # Jobs para filas
  ├── Services/             # Lógica de domínio
  ├── Http/
  │   ├── Controllers/
  │   ├── Requests/         # Validação com FormRequest
  │   └── Resources/        # API Resources
```
