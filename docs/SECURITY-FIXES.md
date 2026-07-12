# Security Audit — Fixes Specification

> Ready for new analysis. Clear for next audit cycle.

## nginx Advisory

Both remaining findings (SEC-001, SEC-002) require nginx config changes on the demo server:

```nginx
# Block composer.json/composer.lock
location ~* ^/(api/)?(composer\.(json|lock)|\.\..*)$ {
    deny all;
}

# Route API through PHP
location /api/ {
    try_files $uri /api/index.php?$query_string;
}
```
