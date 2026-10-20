-- Public section listing and detail lookup indexes; safe to rerun.
CREATE INDEX IF NOT EXISTS idx_saradeeeb_community_public_listing ON community_topics(locale, status, pinned DESC, last_activity_at DESC, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_saradeeeb_community_public_slug ON community_topics(locale, slug) WHERE status='published';
CREATE INDEX IF NOT EXISTS idx_saradeeeb_community_replies_published ON community_replies(topic_id, created_at) WHERE status='published';
CREATE INDEX IF NOT EXISTS idx_saradeeeb_podcast_public_listing ON podcast_episodes(status, published_at DESC);
CREATE INDEX IF NOT EXISTS idx_saradeeeb_podcast_public_slug ON podcast_episodes(slug) WHERE status='published';
CREATE INDEX IF NOT EXISTS idx_saradeeeb_khawatir_public_listing ON khawatir(status, published_at DESC);
CREATE INDEX IF NOT EXISTS idx_saradeeeb_khawatir_public_slug ON khawatir(slug) WHERE status='published';
CREATE INDEX IF NOT EXISTS idx_saradeeeb_podcast_translated_slug ON podcast_episode_translations(locale, slug) WHERE status='published';
CREATE INDEX IF NOT EXISTS idx_saradeeeb_khawatir_translated_slug ON khawatir_translations(locale, slug) WHERE status='published';
