-- Allows easily selecting thread IDs sorted by most recent post
CREATE VIEW IF NOT EXISTS threads_by_latest_post_view AS
    WITH latest_posts AS (
        SELECT MAX(id) id, threadID FROM posts GROUP BY threadID
    )
    SELECT t.id id, p.id postID FROM threads t LEFT JOIN latest_posts p ON t.id = p.threadID ORDER BY postID DESC;
