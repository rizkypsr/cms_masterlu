-- Add parent_id and have_child columns to topics2 table
ALTER TABLE `topics2` 
ADD COLUMN `parent_id` int(11) DEFAULT NULL AFTER `id`,
ADD COLUMN `have_child` int(11) DEFAULT 0 AFTER `seq`;

-- Remove category_id since we're using parent_id for hierarchy
ALTER TABLE `topics2` 
DROP COLUMN `category_id`;
