ALTER TABLE web_accounts ADD COLUMN IF NOT EXISTS role VARCHAR(16) NOT NULL DEFAULT 'member' CHECK (role IN ('member','admin'));
-- Promote an explicitly verified account manually; registration never grants admin.
