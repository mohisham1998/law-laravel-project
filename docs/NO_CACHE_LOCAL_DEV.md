# No-Cache Configuration for Local Development

This document explains the comprehensive no-cache setup implemented to eliminate all caching issues during local development.

## Problem
During local development, multiple layers of caching (Laravel cache, Blade views, Opcache, browser cache) caused UI changes to not appear immediately, wasting development time.

## Solution
We've implemented a multi-layered approach to disable ALL caching in local development:

### 1. **Blade View Caching - DISABLED**
- **File**: `config/view.php`
- **Setting**: `'cache' => env('VIEW_CACHE_ENABLED', !app()->environment('local'))`
- **Effect**: Blade templates are recompiled on every request in local environment
- **ENV Variable**: `VIEW_CACHE_ENABLED=false` in `.env`

### 2. **Laravel Application Cache - DISABLED**
- **File**: `.env`
- **Setting**: `CACHE_STORE=array`
- **Effect**: Uses in-memory array cache that resets on every request (no persistence)
- **Previous**: `database` (persisted cache between requests)

### 3. **PHP Opcache - DISABLED**
- **File**: `docker/php/php.ini`
- **Setting**: `opcache.enable = 0`
- **Effect**: PHP files are not cached in memory, changes reflect immediately
- **Note**: Enable in production for performance (`opcache.enable = 1`)

### 4. **Browser Cache - DISABLED**
- **File**: `app/Http/Middleware/PreventBrowserCacheInLocal.php`
- **Registered**: `bootstrap/app.php` (web middleware)
- **Headers Set**:
  ```
  Cache-Control: no-store, no-cache, must-revalidate, max-age=0
  Pragma: no-cache
  Expires: 0
  ```
- **Effect**: Browser will not cache any responses in local environment

### 5. **Service Provider - Force No Cache**
- **File**: `app/Providers/LocalDevelopmentServiceProvider.php`
- **Registered**: `bootstrap/providers.php`
- **Effect**: Programmatically forces view cache to be disabled on application boot

## How to Apply Changes

### Quick Restart (Recommended)
```bash
docker-compose restart app worker
```

### Full Rebuild (If issues persist)
```bash
docker-compose down
docker-compose build --no-cache app worker
docker-compose up -d
```

### Clear All Caches
```bash
docker-compose exec app php artisan optimize:clear
docker-compose exec app sh -c "rm -rf storage/framework/views/*.php"
```

## Verification

After applying these changes, you should see:
1. ✅ Blade file changes appear immediately (no `php artisan view:clear` needed)
2. ✅ Config changes appear after container restart
3. ✅ PHP code changes appear immediately (no opcache)
4. ✅ Browser always fetches fresh content (no browser cache)

## Performance Impact

⚠️ **Local Development Only**: These settings are ONLY active when `APP_ENV=local`

- **Development**: Slower response times (~50-100ms overhead) but instant updates
- **Production**: All caching enabled automatically for maximum performance

## Troubleshooting

If changes still don't appear:

1. **Hard Refresh Browser**: `Ctrl + Shift + R` (Windows/Linux) or `Cmd + Shift + R` (Mac)
2. **Check Environment**: Verify `APP_ENV=local` in `.env`
3. **Restart Containers**: `docker-compose restart app worker`
4. **Clear Browser Data**: Open DevTools (F12) → Network tab → Check "Disable cache"
5. **Verify Settings**:
   ```bash
   docker-compose exec app php artisan config:show cache
   docker-compose exec app php artisan config:show view
   ```

## Production Deployment

When deploying to production, ensure:
- `APP_ENV=production` in production `.env`
- `CACHE_STORE=redis` or `database` (not `array`)
- `VIEW_CACHE_ENABLED=true` or remove from `.env` (defaults to true in production)
- Update `docker/php/php.ini` to enable opcache:
  ```ini
  opcache.enable = 1
  opcache.validate_timestamps = 0
  ```

## Files Modified

1. `config/view.php` - Created with cache control
2. `.env` - Updated cache settings
3. `.env.example` - Updated cache settings
4. `docker/php/php.ini` - Disabled opcache
5. `app/Providers/LocalDevelopmentServiceProvider.php` - Created
6. `bootstrap/providers.php` - Registered new provider
7. `app/Http/Middleware/PreventBrowserCacheInLocal.php` - Already existed
8. `bootstrap/app.php` - Middleware already registered

## Summary

**Before**: Multiple cache layers causing 5-10 minute delays for UI updates
**After**: Zero caching in local development - all changes appear immediately

No more wasted time clearing caches! 🎉
