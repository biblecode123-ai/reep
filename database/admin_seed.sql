-- Admin Seeder
-- Password for 'admin@mwu.edu.et' is: AdminPassword123!

INSERT INTO users (
    employee_id, 
    full_name, 
    email, 
    password_hash, 
    status, 
    position_title, 
    college_id, 
    department_id, 
    must_change_password
) VALUES (
    'EMP-ADMIN', 
    'System Administrator', 
    'admin@mwu.edu.et', 
    '$2y$12$pnAfjdRkvYPuznE/ixWQeeeNzUMJjTV4yB6OFmIW7jlt8x6f0EWL2', 
    'active', 
    'System Administrator', 
    (SELECT id FROM colleges WHERE code = 'RPEE'), 
    (SELECT id FROM departments WHERE code = 'RM'), 
    1
);

INSERT INTO user_roles (user_id, role_id) 
VALUES (
    (SELECT id FROM users WHERE email = 'admin@mwu.edu.et'), 
    (SELECT id FROM roles WHERE name = 'system_admin')
);

