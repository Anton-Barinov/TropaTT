-- crm.wip-limit: WIP counts are now computed live from `tasks`.
-- The denormalized `crm_wip_counts` counter is no longer used and is removed.
DROP TABLE IF EXISTS `crm_wip_counts`;
