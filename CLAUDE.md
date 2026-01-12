# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

C8 Cart Recovery is a WooCommerce plugin that tracks abandoned shopping carts and sends recovery emails to customers who don't complete checkout. It supports both guest and registered users, with features like guest email capture, customizable email templates, and a recovery link system.

**Requirements:** WordPress 6.0+, PHP 8.1+, WooCommerce 8.0+

## Development Environment

This plugin uses the symlink workflow for local WordPress development with Laravel Herd:
- Plugin source lives in its own Git repository (this folder)
- Symlinked into WordPress at `~/Herd/wordpress/wp-content/plugins/c8-cart-recovery`
- Allows testing against multiple WordPress installations

## Architecture

### Namespace & Autoloading
- Namespace: `Creative8\CartRecovery`
- Custom PSR-4-style autoloader in `includes/autoload.php` with explicit class map
- No Composer autoload for runtime (wordpress-stubs is dev-only for IDE support)

### Core Components
- **Plugin** (`class-plugin.php`): Singleton entry point, initializes all components, handles AJAX email capture, registers WooCommerce email class
- **CartTracker** (`class-cart-tracker.php`): Hooks into WooCommerce cart actions to track/save cart data to database
- **CartRecovery** (`class-cart-recovery.php`): Handles `?c8cr_recover_cart={token}` and `?c8cr_unsubscribe={token}` URL parameters
- **CronHandler** (`class-cron-handler.php`): Processes abandoned carts every 15 minutes, sends emails, runs daily cleanup
- **Admin** (`admin/class-admin.php`): WooCommerce submenu page with tabs (Carts list, Statistics, Settings)
- **AbandonedCart** (`emails/class-abandoned-cart.php`): Extends `WC_Email` for WooCommerce email integration

### Database
Single custom table: `{prefix}c8cr_abandoned_carts`
- Created on activation via `dbDelta()`
- Migration system in main plugin file handles table renames between versions

### Options (wp_options)
- `c8cr_enabled` - Enable/disable tracking (default: 'yes')
- `c8cr_abandonment_time` - Minutes before cart is abandoned (default: 60)
- `c8cr_email_enabled` - Send recovery emails (default: 'yes')
- `c8cr_cleanup_days` - Days to retain data (default: 30)
- `c8cr_db_version` - For migrations

### Cron Events
- `c8cr_process_abandoned_carts` - Every 15 minutes
- `c8cr_cleanup_old_carts` - Daily

### Constants
Defined in main plugin file: `C8CR_VERSION`, `C8CR_PLUGIN_FILE`, `C8CR_PLUGIN_PATH`, `C8CR_PLUGIN_URL`, `C8CR_PLUGIN_BASENAME`

## Key Patterns

### Session Tracking
Sessions are identified as `user_{id}` for logged-in users or `guest_{wc_session_id}` for guests.

### Recovery Tokens
64-character tokens generated via `wp_generate_password()` + `random_bytes()`, stored per cart for unique recovery URLs.

### Email Template Override
Templates in `templates/emails/` can be overridden in theme at `yourtheme/woocommerce/emails/`.

### WooCommerce Compatibility
- HPOS (High-Performance Order Storage) declared compatible
- Email class registered via `woocommerce_email_classes` filter
- Uses WooCommerce session, cart, and customer objects
