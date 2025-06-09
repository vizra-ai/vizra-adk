# ⚙️ Configuration & Deployment

Turn your Vizra SDK from development prototype to production powerhouse. This guide covers everything from basic configuration to enterprise-scale deployment.

## 🎯 Configuration Overview

The Vizra SDK uses Laravel's familiar configuration patterns. All settings live in `config/agent-adk.php` with environment-specific overrides in `.env`.

### Core Configuration Structure

```php
// config/agent-adk.php
return [
    // LLM Provider Settings
    'llm' => [
        'default_provider' => env('AGENT_ADK_DEFAULT_PROVIDER', 'openai'),
        'default_model' => env('AGENT_ADK_DEFAULT_MODEL', 'gpt-4o-mini'),
        'providers' => [
            'openai' => [
                'api_key' => env('OPENAI_API_KEY'),
                'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
                'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo'],
            ],
            'anthropic' => [
                'api_key' => env('ANTHROPIC_API_KEY'),
                'models' => ['claude-3-5-sonnet-20241022', 'claude-3-haiku-20240307'],
            ],
            'cohere' => [
                'api_key' => env('COHERE_API_KEY'),
                'models' => ['command-r', 'command-r-plus'],
            ],
        ],
    ],

    // Vector Memory Configuration
    'vector_memory' => [
        'driver' => env('VECTOR_MEMORY_DRIVER', 'meilisearch'),
        'drivers' => [
            'meilisearch' => [
                'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
                'api_key' => env('MEILISEARCH_API_KEY'),
                'index_prefix' => env('MEILISEARCH_INDEX_PREFIX', 'agent_vectors_'),
            ],
            'pinecone' => [
                'api_key' => env('PINECONE_API_KEY'),
                'environment' => env('PINECONE_ENVIRONMENT'),
                'index_name' => env('PINECONE_INDEX_NAME', 'agent-memory'),
            ],
        ],
    ],

    // Default Generation Parameters
    'default_generation_params' => [
        'temperature' => 0.7,
        'max_tokens' => 1000,
        'top_p' => null,
        'frequency_penalty' => null,
        'presence_penalty' => null,
    ],

    // Session & Memory Settings
    'session' => [
        'driver' => env('AGENT_SESSION_DRIVER', 'database'),
        'table' => 'agent_sessions',
        'cleanup_after_days' => 30,
    ],

    // Performance & Limits
    'limits' => [
        'max_conversation_length' => 50,
        'max_tool_calls_per_message' => 5,
        'max_memory_results' => 10,
        'request_timeout' => 60,
    ],

    // UI Configuration
    'ui' => [
        'enabled' => env('AGENT_ADK_UI_ENABLED', true),
        'prefix' => 'ai-adk',
        'middleware' => ['web'],
        'theme' => 'default',
    ],
];
```

## 🔧 Environment Configuration

### Development Environment

```env
# Basic LLM Setup
AGENT_ADK_DEFAULT_PROVIDER=openai
AGENT_ADK_DEFAULT_MODEL=gpt-4o-mini
OPENAI_API_KEY=sk-your-development-key

# Vector Memory (Local for development)
VECTOR_MEMORY_DRIVER=local
VECTOR_MEMORY_LOCAL_PATH=storage/vector_memory

# Session Storage
AGENT_SESSION_DRIVER=database

# UI Settings
AGENT_ADK_UI_ENABLED=true

# Debug and Logging
AGENT_ADK_DEBUG=true
AGENT_ADK_LOG_LEVEL=debug
```

### Staging Environment

```env
# Production-like LLM Setup
AGENT_ADK_DEFAULT_PROVIDER=openai
AGENT_ADK_DEFAULT_MODEL=gpt-4o-mini
OPENAI_API_KEY=sk-your-staging-key

# Managed Vector Memory
VECTOR_MEMORY_DRIVER=meilisearch
MEILISEARCH_HOST=https://staging-meilisearch.yourapp.com
MEILISEARCH_API_KEY=your-staging-meilisearch-key

# Performance Settings
AGENT_ADK_REQUEST_TIMEOUT=30
AGENT_ADK_MAX_CONCURRENT_REQUESTS=5

# Monitoring
AGENT_ADK_DEBUG=false
AGENT_ADK_LOG_LEVEL=info
```

### Production Environment

```env
# Production LLM Setup
AGENT_ADK_DEFAULT_PROVIDER=openai
AGENT_ADK_DEFAULT_MODEL=gpt-4o
OPENAI_API_KEY=sk-your-production-key

# Scalable Vector Memory
VECTOR_MEMORY_DRIVER=pinecone
PINECONE_API_KEY=your-pinecone-key
PINECONE_ENVIRONMENT=us-west1-gcp
PINECONE_INDEX_NAME=production-agent-memory

# Performance & Reliability
AGENT_ADK_REQUEST_TIMEOUT=60
AGENT_ADK_MAX_CONCURRENT_REQUESTS=10
AGENT_ADK_RETRY_ATTEMPTS=3
AGENT_ADK_CIRCUIT_BREAKER_ENABLED=true

# Security
AGENT_ADK_UI_ENABLED=false  # Disable UI in production
AGENT_ADK_API_RATE_LIMIT=100
AGENT_ADK_ENCRYPTION_KEY=your-32-char-encryption-key

# Monitoring & Logging
AGENT_ADK_DEBUG=false
AGENT_ADK_LOG_LEVEL=warning
AGENT_ADK_TELEMETRY_ENABLED=true
SENTRY_DSN=your-sentry-dsn
```

## 🏗️ Deployment Strategies

### 1. Single Server Deployment (Small to Medium Scale)

**Best for:** 1-10k conversations/day, small teams

```yaml
# docker-compose.yml
version: "3.8"
services:
  app:
    build: .
    ports:
      - "80:80"
    environment:
      - APP_ENV=production
      - AGENT_ADK_DEFAULT_PROVIDER=openai
      - OPENAI_API_KEY=${OPENAI_API_KEY}
    depends_on:
      - database
      - meilisearch
      - redis

  database:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}
      MYSQL_DATABASE: ${DB_DATABASE}
    volumes:
      - mysql_data:/var/lib/mysql

  meilisearch:
    image: getmeili/meilisearch:v1.5
    environment:
      MEILI_MASTER_KEY: ${MEILISEARCH_API_KEY}
    volumes:
      - meilisearch_data:/meili_data

  redis:
    image: redis:7-alpine
    volumes:
      - redis_data:/data

volumes:
  mysql_data:
  meilisearch_data:
  redis_data:
```

### 2. Horizontal Scaling (Medium to Large Scale)

**Best for:** 10k-100k conversations/day, growing businesses

```yaml
# docker-compose.production.yml
version: "3.8"
services:
  # Load Balancer
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
      - ./ssl:/etc/ssl
    depends_on:
      - app

  # Application Servers (Scale horizontally)
  app:
    build: .
    deploy:
      replicas: 3
    environment:
      - APP_ENV=production
      - QUEUE_CONNECTION=redis
      - AGENT_ADK_VECTOR_MEMORY_DRIVER=pinecone
    depends_on:
      - database
      - redis

  # Background Job Processing
  queue:
    build: .
    command: php artisan queue:work --sleep=3 --tries=3
    deploy:
      replicas: 2
    environment:
      - APP_ENV=production
      - QUEUE_CONNECTION=redis

  # Shared Services
  database:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql

  redis:
    image: redis:7-alpine
    command: redis-server --appendonly yes
    volumes:
      - redis_data:/data

volumes:
  mysql_data:
  redis_data:
```

### 3. Microservices Architecture (Enterprise Scale)

**Best for:** 100k+ conversations/day, enterprise requirements

```yaml
# kubernetes/agent-adk-deployment.yml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: agent-adk-app
spec:
  replicas: 5
  selector:
    matchLabels:
      app: agent-adk-app
  template:
    metadata:
      labels:
        app: agent-adk-app
    spec:
      containers:
        - name: app
          image: your-registry/agent-adk:latest
          env:
            - name: APP_ENV
              value: "production"
            - name: OPENAI_API_KEY
              valueFrom:
                secretKeyRef:
                  name: agent-adk-secrets
                  key: openai-api-key
          resources:
            requests:
              cpu: 200m
              memory: 512Mi
            limits:
              cpu: 500m
              memory: 1Gi
---
apiVersion: v1
kind: Service
metadata:
  name: agent-adk-service
spec:
  selector:
    app: agent-adk-app
  ports:
    - port: 80
      targetPort: 8000
  type: LoadBalancer
```

## 🚀 Laravel Forge Deployment

### Forge Configuration

Laravel Forge makes deployment straightforward:

```bash
# Forge deployment script
cd /home/forge/your-app.com

# Standard Laravel deployment
git pull origin $FORGE_SITE_BRANCH
$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader

# Agent ADK specific steps
if [ -f artisan ]; then
    # Update vector memory indexes
    php artisan vizra:vector-memory:optimize

    # Warm up agent caches
    php artisan vizra:cache:warm

    # Run any pending evaluations
    php artisan vizra:evaluation:cleanup
fi

# Standard Laravel finalization
$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan cache:clear
$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan view:cache
$FORGE_PHP artisan queue:restart

# Reload PHP-FPM
( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock
```

### Forge Environment Variables

```bash
# In Forge Environment settings
AGENT_ADK_DEFAULT_PROVIDER=openai
OPENAI_API_KEY=sk-your-forge-production-key
VECTOR_MEMORY_DRIVER=pinecone
PINECONE_API_KEY=your-pinecone-key
PINECONE_ENVIRONMENT=us-west1-gcp
AGENT_ADK_UI_ENABLED=false
```

## ☁️ Cloud Deployment Options

### AWS Deployment

```yaml
# aws/cloudformation-template.yml
AWSTemplateFormatVersion: "2010-09-09"
Description: "Vizra SDK Infrastructure"

Resources:
  # ECS Cluster for Application
  AgentADKCluster:
    Type: AWS::ECS::Cluster
    Properties:
      ClusterName: agent-adk-production

  # Application Load Balancer
  AgentADKLoadBalancer:
    Type: AWS::ElasticLoadBalancingV2::LoadBalancer
    Properties:
      Type: application
      Scheme: internet-facing
      SecurityGroups: [!Ref WebSecurityGroup]
      Subnets: [!Ref PublicSubnet1, !Ref PublicSubnet2]

  # RDS Database
  AgentADKDatabase:
    Type: AWS::RDS::DBInstance
    Properties:
      DBInstanceClass: db.t3.medium
      Engine: mysql
      EngineVersion: "8.0"
      AllocatedStorage: 100
      MasterUsername: !Ref DBUsername
      MasterUserPassword: !Ref DBPassword

  # ElastiCache for Redis
  AgentADKRedis:
    Type: AWS::ElastiCache::CacheCluster
    Properties:
      CacheNodeType: cache.t3.micro
      Engine: redis
      NumCacheNodes: 1

  # OpenSearch for Vector Memory (Alternative to Pinecone)
  AgentADKVectorSearch:
    Type: AWS::OpenSearchService::Domain
    Properties:
      DomainName: agent-adk-vectors
      ElasticsearchVersion: "7.10"
      ClusterConfig:
        InstanceType: t3.small.search
        InstanceCount: 1
```

### Google Cloud Platform

```yaml
# gcp/cloudbuild.yml
steps:
  # Build Docker image
  - name: "gcr.io/cloud-builders/docker"
    args: ["build", "-t", "gcr.io/$PROJECT_ID/agent-adk:$COMMIT_SHA", "."]

  # Push to Container Registry
  - name: "gcr.io/cloud-builders/docker"
    args: ["push", "gcr.io/$PROJECT_ID/agent-adk:$COMMIT_SHA"]

  # Deploy to Cloud Run
  - name: "gcr.io/cloud-builders/gcloud"
    args:
      - "run"
      - "deploy"
      - "agent-adk"
      - "--image"
      - "gcr.io/$PROJECT_ID/agent-adk:$COMMIT_SHA"
      - "--region"
      - "us-central1"
      - "--platform"
      - "managed"
      - "--set-env-vars"
      - "APP_ENV=production,OPENAI_API_KEY=$$OPENAI_API_KEY"
    secretEnv: ["OPENAI_API_KEY"]

availableSecrets:
  secretManager:
    - versionName: projects/$PROJECT_ID/secrets/openai-api-key/versions/latest
      env: "OPENAI_API_KEY"
```

## 📊 Performance Optimization

### Application-Level Optimization

```php
// config/agent-adk.php
'performance' => [
    // Cache LLM responses for repeated queries
    'response_caching' => [
        'enabled' => env('AGENT_ADK_CACHE_RESPONSES', true),
        'ttl' => 3600, // 1 hour
        'cache_store' => 'redis',
    ],

    // Connection pooling for LLM providers
    'connection_pooling' => [
        'enabled' => true,
        'max_connections' => 10,
        'idle_timeout' => 30,
    ],

    // Vector memory optimization
    'vector_memory' => [
        'batch_size' => 100,
        'index_refresh_interval' => 300, // 5 minutes
        'search_cache_ttl' => 600, // 10 minutes
    ],

    // Agent execution optimization
    'agent_execution' => [
        'parallel_tool_calls' => true,
        'tool_timeout' => 15,
        'max_memory_context' => 5000, // tokens
    ],
],
```

### Database Optimization

```php
// database/migrations/optimize_agent_tables.php
public function up()
{
    Schema::table('agent_sessions', function (Blueprint $table) {
        // Add indexes for common queries
        $table->index(['user_id', 'created_at']);
        $table->index(['agent_name', 'status']);
        $table->index('last_activity_at');
    });

    Schema::table('agent_messages', function (Blueprint $table) {
        $table->index(['session_id', 'created_at']);
        $table->index(['role', 'created_at']);
    });

    Schema::table('agent_memories', function (Blueprint $table) {
        $table->index(['agent_name', 'namespace']);
        $table->index(['source', 'created_at']);
        $table->index('embedding_provider');
    });

    // Add partitioning for large tables (MySQL 8.0+)
    DB::statement('
        ALTER TABLE agent_messages
        PARTITION BY RANGE (YEAR(created_at)) (
            PARTITION p2024 VALUES LESS THAN (2025),
            PARTITION p2025 VALUES LESS THAN (2026),
            PARTITION p_future VALUES LESS THAN MAXVALUE
        )
    ');
}
```

### Redis Configuration

```bash
# redis.conf optimizations for Agent ADK
maxmemory 2gb
maxmemory-policy allkeys-lru

# Enable persistence for conversation data
save 900 1
save 300 10
save 60 10000

# Optimize for conversation patterns
tcp-keepalive 60
timeout 0

# Enable compression
rdbcompression yes
```

## 🔐 Security Configuration

### API Key Management

```php
// config/agent-adk.php
'security' => [
    // Encrypt sensitive data at rest
    'encryption' => [
        'enabled' => env('AGENT_ADK_ENCRYPTION_ENABLED', true),
        'key' => env('AGENT_ADK_ENCRYPTION_KEY'),
        'cipher' => 'aes-256-gcm',
    ],

    // Rate limiting
    'rate_limiting' => [
        'enabled' => true,
        'requests_per_minute' => 60,
        'burst_limit' => 10,
    ],

    // API key rotation
    'api_key_rotation' => [
        'enabled' => true,
        'rotation_days' => 90,
        'notification_days' => 7,
    ],

    // Content filtering
    'content_filtering' => [
        'enabled' => true,
        'providers' => ['openai_moderation'],
        'block_harmful_content' => true,
    ],
],
```

### Access Control

```php
// app/Http/Middleware/AgentADKAuth.php
class AgentADKAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Verify API key if using API
        if ($request->is('api/agent-adk/*')) {
            $apiKey = $request->bearerToken();
            if (!$this->isValidApiKey($apiKey)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        // Check user permissions for UI access
        if ($request->is('ai-adk/*') && !$request->user()?->can('access-agent-adk')) {
            abort(403, 'Access denied to Agent ADK interface');
        }

        return $next($request);
    }
}
```

## 📈 Monitoring & Observability

### Application Metrics

```php
// app/Services/AgentMetricsService.php
class AgentMetricsService
{
    public function recordAgentInteraction(string $agentName, float $responseTime, bool $success): void
    {
        // Record to your metrics system (Prometheus, DataDog, etc.)
        Metrics::increment('agent.interactions.total', [
            'agent' => $agentName,
            'status' => $success ? 'success' : 'failure',
        ]);

        Metrics::histogram('agent.response_time', $responseTime, [
            'agent' => $agentName,
        ]);
    }

    public function recordTokenUsage(string $provider, string $model, int $tokens, float $cost): void
    {
        Metrics::increment('agent.tokens.total', $tokens, [
            'provider' => $provider,
            'model' => $model,
        ]);

        Metrics::increment('agent.cost.total', $cost, [
            'provider' => $provider,
            'model' => $model,
        ]);
    }
}
```

### Health Checks

```php
// app/Http/Controllers/HealthController.php
class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $status = 'healthy';
        $checks = [];

        // Check database connectivity
        try {
            DB::connection()->getPdo();
            $checks['database'] = 'ok';
        } catch (Exception $e) {
            $checks['database'] = 'error';
            $status = 'unhealthy';
        }

        // Check LLM provider connectivity
        try {
            $response = Http::timeout(5)->get('https://api.openai.com/v1/models', [
                'Authorization' => 'Bearer ' . config('agent-adk.llm.providers.openai.api_key')
            ]);
            $checks['llm_provider'] = $response->successful() ? 'ok' : 'error';
        } catch (Exception $e) {
            $checks['llm_provider'] = 'error';
            $status = 'unhealthy';
        }

        // Check vector memory service
        try {
            $available = VectorMemory::driver()->isAvailable();
            $checks['vector_memory'] = $available ? 'ok' : 'error';
        } catch (Exception $e) {
            $checks['vector_memory'] = 'error';
            $status = 'unhealthy';
        }

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
        ], $status === 'healthy' ? 200 : 503);
    }
}
```

## 🔄 Backup & Recovery

### Automated Backups

```bash
#!/bin/bash
# scripts/backup-agent-data.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/agent-adk"

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
mysqldump -h $DB_HOST -u $DB_USERNAME -p$DB_PASSWORD $DB_DATABASE \
  --single-transaction --routines --triggers > $BACKUP_DIR/database_$DATE.sql

# Backup vector memory data
if [ "$VECTOR_MEMORY_DRIVER" = "meilisearch" ]; then
    curl -X POST "$MEILISEARCH_HOST/dumps" \
         -H "Authorization: Bearer $MEILISEARCH_API_KEY" \
         -H "Content-Type: application/json"
fi

# Backup application files
tar -czf $BACKUP_DIR/app_$DATE.tar.gz \
    --exclude=node_modules \
    --exclude=vendor \
    --exclude=storage/logs \
    /path/to/your/app

# Upload to cloud storage
aws s3 sync $BACKUP_DIR s3://your-backup-bucket/agent-adk/$DATE/

# Cleanup old backups (keep last 30 days)
find $BACKUP_DIR -type f -mtime +30 -delete
```

### Disaster Recovery

```yaml
# ansible/disaster-recovery-playbook.yml
---
- name: Agent ADK Disaster Recovery
  hosts: production
  tasks:
    - name: Stop application services
      systemd:
        name: "{{ item }}"
        state: stopped
      loop:
        - nginx
        - php8.1-fpm
        - laravel-worker

    - name: Restore database from backup
      mysql_db:
        name: "{{ db_name }}"
        state: import
        target: "/backups/latest/database.sql"

    - name: Restore application code
      unarchive:
        src: "/backups/latest/app.tar.gz"
        dest: "/var/www"
        remote_src: yes

    - name: Run Laravel migrations
      command: php artisan migrate --force
      args:
        chdir: /var/www/your-app

    - name: Rebuild vector memory indexes
      command: php artisan vizra:vector-memory:rebuild
      args:
        chdir: /var/www/your-app

    - name: Start application services
      systemd:
        name: "{{ item }}"
        state: started
      loop:
        - php8.1-fpm
        - laravel-worker
        - nginx
```

## 🎯 Environment-Specific Optimizations

### Development Optimizations

```php
// config/agent-adk.php (development)
'development' => [
    'mock_llm_responses' => env('MOCK_LLM_RESPONSES', false),
    'cache_disabled' => true,
    'verbose_logging' => true,
    'debug_toolbar' => true,
    'local_vector_memory' => true,
],
```

### Production Optimizations

```php
// config/agent-adk.php (production)
'production' => [
    'aggressive_caching' => true,
    'connection_pooling' => true,
    'async_processing' => true,
    'circuit_breaker' => true,
    'telemetry_sampling' => 0.1, // Sample 10% of requests
],
```

---

<p align="center">
<strong>Ready to see real-world examples in action?</strong><br>
<a href="examples.md">Next: Examples & Use Cases →</a>
</p>
