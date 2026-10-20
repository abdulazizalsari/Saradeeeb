CREATE TABLE IF NOT EXISTS web_community_topics (
 id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
 author_id BIGINT NOT NULL REFERENCES web_accounts(id) ON DELETE CASCADE,
 locale VARCHAR(8) NOT NULL DEFAULT 'ar',
 title VARCHAR(190) NOT NULL,
 slug VARCHAR(230) NOT NULL,
 content TEXT NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'open' CHECK (status IN ('open','closed','hidden')),
 created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
 UNIQUE(locale,slug)
);
CREATE INDEX IF NOT EXISTS idx_web_community_topics_public ON web_community_topics(locale,status,created_at DESC);
CREATE TABLE IF NOT EXISTS web_community_replies (
 id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
 topic_id BIGINT NOT NULL REFERENCES web_community_topics(id) ON DELETE CASCADE,
 author_id BIGINT NOT NULL REFERENCES web_accounts(id) ON DELETE CASCADE,
 content TEXT NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'visible' CHECK (status IN ('visible','hidden')),
 created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_web_community_replies_public ON web_community_replies(topic_id,status,created_at);