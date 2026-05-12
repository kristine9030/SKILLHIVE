-- Reset test_adviser@gmail.com password to "password"
-- Hash: $2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86FW0Hn5JrFm (bcrypt of "password")
UPDATE internship_adviser 
SET password_hash = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86FW0Hn5JrFm' 
WHERE email = 'test_adviser@gmail.com';

SELECT 'Password reset complete!' as status;
SELECT adviser_id, email FROM internship_adviser WHERE email = 'test_adviser@gmail.com';
