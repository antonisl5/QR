ALTER TABLE `users` ADD COLUMN `is_active` BOOLEAN DEFAULT TRUE COMMENT 'Soft delete: TRUE=active, FALSE=deactivated' AFTER `store_id`;
