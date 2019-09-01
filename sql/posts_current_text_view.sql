CREATE VIEW IF NOT EXISTS posts_current_text_view AS
    WITH edits AS (
        SELECT MAX(id) id, text, postID FROM posts_edits GROUP BY postID
    )
    SELECT p.id id, IFNULL(e.text, p.text) text FROM posts p LEFT JOIN edits e ON p.id = e.postID;
