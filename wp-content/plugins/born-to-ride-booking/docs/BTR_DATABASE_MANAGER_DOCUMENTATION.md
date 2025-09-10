# BTR_Database_Manager Documentation

## Overview

`BTR_Database_Manager` è una classe singleton thread-safe che gestisce tutte le operazioni sulla tabella `btr_order_shares` nel plugin Born to Ride Booking. La classe implementa il pattern CRUD completo con funzionalità avanzate come transazioni, soft delete, caching e query ottimizzate.

## Table Structure

La tabella `btr_order_shares` gestisce le quote di pagamento individuali per ordini di gruppo:

```sql
btr_order_shares
├── id (PK, AUTO_INCREMENT)
├── order_id (FK -> wp_posts.ID)
├── participant_id
├── participant_name
├── participant_email
├── participant_phone
├── amount_assigned
├── amount_paid
├── currency
├── payment_method
├── payment_status (enum)
├── payment_link
├── payment_token (UNIQUE)
├── token_expires_at
├── transaction_id
├── paid_at
├── failed_at
├── failure_reason
├── reminder_sent_at
├── reminder_count
├── next_reminder_at
├── notes
├── metadata (JSON)
├── created_at
├── updated_at
├── deleted_at (soft delete)
```

## Usage

### Getting Instance

```php
// Ottieni istanza singleton
$db_manager = BTR_Database_Manager::get_instance();
```

### Create Operations

#### Single Insert
```php
$share_id = $db_manager->create([
    'order_id' => 12345,
    'participant_id' => 1,
    'participant_name' => 'Mario Rossi',
    'participant_email' => 'mario@example.com',
    'amount_assigned' => 150.00,
    'metadata' => [
        'room_type' => 'double',
        'check_in' => '2025-02-01'
    ]
]);
```

#### Bulk Insert with Transaction
```php
try {
    $ids = $db_manager->create_bulk([
        [
            'order_id' => 12345,
            'participant_id' => 1,
            'participant_name' => 'Mario Rossi',
            'participant_email' => 'mario@example.com',
            'amount_assigned' => 150.00
        ],
        [
            'order_id' => 12345,
            'participant_id' => 2,
            'participant_name' => 'Luigi Bianchi',
            'participant_email' => 'luigi@example.com',
            'amount_assigned' => 150.00
        ]
    ]);
} catch (Exception $e) {
    // Handle error - transaction rolled back automatically
}
```

### Read Operations

#### Read by ID
```php
$share = $db_manager->read($share_id);
// Include soft deleted records
$share = $db_manager->read($share_id, true);
```

#### Read by Order
```php
// Get all shares for an order
$shares = $db_manager->get_by_order($order_id);

// With filters
$shares = $db_manager->get_by_order($order_id, [
    'status' => 'pending',
    'orderby' => 'participant_name',
    'order' => 'ASC',
    'limit' => 10,
    'offset' => 0
]);
```

#### Read by Email
```php
$shares = $db_manager->get_by_email('mario@example.com', [
    'status' => 'paid',
    'limit' => 5
]);
```

#### Read by Payment Token
```php
$share = $db_manager->get_by_token($payment_token);
```

#### Get Pending Reminders
```php
$shares_needing_reminder = $db_manager->get_pending_reminders(100);
```

### Update Operations

#### Basic Update
```php
$success = $db_manager->update($share_id, [
    'payment_status' => 'paid',
    'amount_paid' => 150.00,
    'payment_method' => 'credit_card',
    'transaction_id' => 'TXN123456'
]);
```

#### Update Payment Status
```php
$success = $db_manager->update_payment_status(
    $share_id, 
    'failed',
    ['failure_reason' => 'Card declined']
);
```

#### Increment Reminder Count
```php
$next_reminder = new DateTime('+3 days');
$success = $db_manager->increment_reminder_count($share_id, $next_reminder);
```

### Delete Operations

#### Soft Delete (Default)
```php
// Marks record as deleted (sets deleted_at timestamp)
$success = $db_manager->delete($share_id);
```

#### Hard Delete
```php
// Permanently removes record from database
$success = $db_manager->delete($share_id, true);
```

### Query Operations

#### Custom Query
```php
// Custom WHERE clause
$results = $db_manager->query(
    "amount_assigned > 100 AND payment_status = 'pending'",
    [
        'orderby' => 'created_at',
        'order' => 'DESC',
        'limit' => 20
    ]
);
```

#### Count Records
```php
// Count with conditions
$count = $db_manager->count([
    'order_id' => 12345,
    'payment_status' => 'paid',
    'deleted_at' => null
]);
```

#### Get Statistics
```php
$stats = $db_manager->get_order_statistics($order_id);
/*
Returns:
- total_shares
- paid_shares
- pending_shares
- failed_shares
- total_amount
- total_paid
- confirmed_amount
- completion_percentage
- payment_percentage
*/
```

### Transaction Support

```php
try {
    $result = $db_manager->transaction(function($manager) {
        // All operations here are atomic
        $id1 = $manager->create([...]);
        $manager->update($id1, [...]);
        
        $id2 = $manager->create([...]);
        
        if (some_condition_fails()) {
            throw new Exception('Rollback transaction');
        }
        
        return [$id1, $id2];
    });
} catch (Exception $e) {
    // Transaction rolled back automatically
}
```

### Caching

The class includes automatic caching for frequently accessed data:

```php
// Clear all cache
$db_manager->clear_cache();

// Cache is automatically cleared when data changes
```

## Advanced Features

### Pagination

```php
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$shares = $db_manager->get_by_status('pending', [
    'limit' => $per_page,
    'offset' => $offset
]);
```

### Metadata Support

```php
// Store complex data as metadata
$share_id = $db_manager->create([
    'order_id' => 12345,
    'participant_id' => 1,
    'participant_name' => 'Test User',
    'participant_email' => 'test@example.com',
    'amount_assigned' => 100,
    'metadata' => [
        'room_preferences' => ['non-smoking', 'ground-floor'],
        'dietary_restrictions' => 'vegetarian',
        'special_requests' => 'Late check-in'
    ]
]);

// Metadata is automatically serialized/deserialized
$share = $db_manager->read($share_id);
$room_prefs = $share->metadata['room_preferences'];
```

### Automatic Token Generation

Payment tokens are automatically generated for security:

```php
$share_id = $db_manager->create([
    'order_id' => 12345,
    'participant_id' => 1,
    'participant_name' => 'Test User',
    'participant_email' => 'test@example.com',
    'amount_assigned' => 100
    // payment_token and token_expires_at are auto-generated
]);

$share = $db_manager->read($share_id);
echo $share->payment_token; // Secure hash token
echo $share->token_expires_at; // +7 days from creation
```

## Security Features

1. **Prepared Statements**: All queries use WordPress prepared statements
2. **Input Validation**: Comprehensive validation and sanitization
3. **Soft Delete**: GDPR compliance with data retention
4. **Token Security**: Automatic secure token generation with expiration
5. **Transaction Support**: Atomic operations for data integrity

## Performance Optimization

1. **Indexed Columns**: Optimized for common query patterns
2. **Caching**: Built-in caching for repeated queries
3. **Batch Operations**: Bulk insert support
4. **Efficient Queries**: Composite indexes for complex queries

## Error Handling

```php
// The class logs errors automatically
$share_id = $db_manager->create($invalid_data);
if (!$share_id) {
    // Check WordPress error log for details
}

// With transactions, use try-catch
try {
    $result = $db_manager->transaction(function($manager) {
        // Operations
    });
} catch (Exception $e) {
    error_log('Transaction failed: ' . $e->getMessage());
}
```

## Hooks and Filters

The class automatically hooks into WordPress for:
- Database installation/updates on `plugins_loaded`
- Cache clearing on `btr_clear_database_cache` action

## Testing

Run the included test file to verify functionality:
```
/wp-content/plugins/born-to-ride-booking/tests/test-database-manager.php
```

## Best Practices

1. **Always use the singleton instance**
   ```php
   $db_manager = BTR_Database_Manager::get_instance();
   ```

2. **Use transactions for multiple related operations**
   ```php
   $db_manager->transaction(function($manager) {
       // Multiple operations
   });
   ```

3. **Check return values**
   ```php
   if ($share_id = $db_manager->create($data)) {
       // Success
   } else {
       // Handle error
   }
   ```

4. **Use appropriate read methods**
   ```php
   // Single record
   $share = $db_manager->read($id);
   
   // Multiple records with specific criteria
   $shares = $db_manager->get_by_order($order_id, ['status' => 'pending']);
   ```

5. **Leverage built-in methods**
   ```php
   // Instead of manual status update
   $db_manager->update_payment_status($id, 'paid', ['transaction_id' => 'TXN123']);
   ```

## Migration from Direct SQL

If migrating from direct SQL queries:

```php
// Before
global $wpdb;
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}btr_order_shares WHERE order_id = %d",
    $order_id
));

// After
$db_manager = BTR_Database_Manager::get_instance();
$results = $db_manager->get_by_order($order_id);
```

## Extending the Class

To add custom methods, extend the class:

```php
class My_Extended_Manager extends BTR_Database_Manager {
    public function get_overdue_payments($days = 7) {
        return $this->query(
            "payment_status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            ['limit' => 100]
        );
    }
}
```