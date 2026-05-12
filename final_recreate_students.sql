-- Delete all dummy students and their assignments
DELETE FROM adviser_assignment WHERE student_id IN (
  SELECT student_id FROM student WHERE student_number LIKE '2_-%' OR student_number LIKE '20-%'
);

DELETE FROM student WHERE student_number LIKE '2_-%' OR student_number LIKE '20-%';

-- Insert for 2024-2025 (24-00001, 24-00002, etc.) - ALL 4TH YEAR
INSERT INTO student (student_number, first_name, last_name, email, program, department, track, section, year_level, availability_status, school_year_id, archived_at) VALUES
('24-00001', 'Juan', 'Cruz', 'juan.cruz@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Available', 1, NULL),
('24-00002', 'Maria', 'Santos', 'maria.santos@student.edu', 'BS Information Technology', 'IT', 'Networking', 'NT', '4th Year', 'Currently Interning', 1, NULL),
('24-00003', 'Pedro', 'Reyes', 'pedro.reyes@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Available', 1, NULL),
('24-00004', 'Ana', 'Garcia', 'ana.garcia@student.edu', 'BS Information Technology', 'IT', 'Networking', 'NT', '4th Year', 'Unavailable', 1, NULL),
('24-00005', 'Carlos', 'Lopez', 'carlos.lopez@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Available', 1, NULL),
('24-00006', 'Rosa', 'Fernandez', 'rosa.fernandez@student.edu', 'BS Information Technology', 'IT', 'Networking', 'NT', '4th Year', 'Currently Interning', 1, NULL);

-- Insert for 2025-2026 (25-00001, 25-00002, etc.) - ALL 4TH YEAR
INSERT INTO student (student_number, first_name, last_name, email, program, department, track, section, year_level, availability_status, school_year_id, archived_at) VALUES
('25-00001', 'Miguel', 'Tanque', 'miguel.tanque@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Available', 2, NULL),
('25-00002', 'Liza', 'Diaz', 'liza.diaz@student.edu', 'BS Information Technology', 'IT', 'Networking', 'NT', '4th Year', 'Available', 2, NULL),
('25-00003', 'Diego', 'Morales', 'diego.morales@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Currently Interning', 2, NULL),
('25-00004', 'Sofia', 'Ramirez', 'sofia.ramirez@student.edu', 'BS Information Technology', 'IT', 'Networking', 'NT', '4th Year', 'Available', 2, NULL),
('25-00005', 'Roberto', 'Castro', 'roberto.castro@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Unavailable', 2, NULL),
('25-00006', 'Elena', 'Valdez', 'elena.valdez@student.edu', 'BS Information Technology', 'IT', 'Networking', 'NT', '4th Year', 'Available', 2, NULL),
('25-00007', 'Lucas', 'Jimenez', 'lucas.jimenez@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Currently Interning', 2, NULL);

-- Insert for 2026-2027 (26-00001, 26-00002, etc.) - ALL 4TH YEAR
INSERT INTO student (student_number, first_name, last_name, email, program, department, track, section, year_level, availability_status, school_year_id, archived_at) VALUES
('26-00001', 'Fernando', 'Alonso', 'fernando.alonso@student.edu', 'BS Information Technology', 'IT', 'Networking', 'NT', '4th Year', 'Available', 3, '2026-05-12 10:00:00'),
('26-00002', 'Isabella', 'Montero', 'isabella.montero@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Currently Interning', 3, NULL),
('26-00003', 'Adrian', 'Perez', 'adrian.perez@student.edu', 'BS Information Technology', 'IT', 'Networking', 'NT', '4th Year', 'Available', 3, '2026-05-12 10:00:00'),
('26-00004', 'Valentina', 'Herrera', 'valentina.herrera@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Available', 3, NULL),
('26-00005', 'Marco', 'Gutierrez', 'marco.gutierrez@student.edu', 'BS Information Technology', 'IT', 'Networking', 'NT', '4th Year', 'Unavailable', 3, NULL),
('26-00006', 'Gabriela', 'Rios', 'gabriela.rios@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Available', 3, NULL);

-- Insert for 2027-2028 (27-00001, 27-00002, etc.) - ALL 4TH YEAR
INSERT INTO student (student_number, first_name, last_name, email, program, department, track, section, year_level, availability_status, school_year_id, archived_at) VALUES
('27-00001', 'Santiago', 'Moreno', 'santiago.moreno@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Available', 4, '2026-05-12 10:00:00'),
('27-00002', 'Catalina', 'Flores', 'catalina.flores@student.edu', 'BS Information Technology', 'IT', 'Networking', 'NT', '4th Year', 'Currently Interning', 4, '2026-05-12 10:00:00'),
('27-00003', 'Javier', 'Soto', 'javier.soto@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Available', 4, NULL),
('27-00004', 'Veronica', 'Ruiz', 'veronica.ruiz@student.edu', 'BS Information Technology', 'IT', 'Networking', 'NT', '4th Year', 'Available', 4, NULL),
('27-00005', 'Francisco', 'Medina', 'francisco.medina@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Unavailable', 4, '2026-05-12 10:00:00'),
('27-00006', 'Alejandra', 'Vargas', 'alejandra.vargas@student.edu', 'BS Information Technology', 'IT', 'Networking', 'NT', '4th Year', 'Available', 4, NULL);

-- Insert for 2028-2029 (28-00001, 28-00002, etc.) - ALL 4TH YEAR
INSERT INTO student (student_number, first_name, last_name, email, program, department, track, section, year_level, availability_status, school_year_id, archived_at) VALUES
('28-00001', 'Mateo', 'Acosta', 'mateo.acosta@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Available', 5, NULL),
('28-00002', 'Martina', 'Nava', 'martina.nava@student.edu', 'BS Information Technology', 'IT', 'Networking', 'NT', '4th Year', 'Currently Interning', 5, NULL),
('28-00003', 'Antonio', 'Rojas', 'antonio.rojas@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Available', 5, '2026-05-12 10:00:00'),
('28-00004', 'Claudia', 'Rivas', 'claudia.rivas@student.edu', 'BS Information Technology', 'IT', 'Networking', 'NT', '4th Year', 'Available', 5, '2026-05-12 10:00:00'),
('28-00005', 'Ricardo', 'Palacios', 'ricardo.palacios@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Unavailable', 5, NULL),
('28-00006', 'Pamela', 'Iglesias', 'pamela.iglesias@student.edu', 'BS Information Technology', 'IT', 'Networking', 'NT', '4th Year', 'Available', 5, NULL);

-- Insert for 2020-2021 (20-00001, 20-00002, etc.) - ALL 4TH YEAR - Alumni
INSERT INTO student (student_number, first_name, last_name, email, program, department, track, section, year_level, availability_status, school_year_id, archived_at) VALUES
('20-00001', 'Rodrigo', 'Muniz', 'rodrigo.muniz@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Available', 6, '2026-05-12 10:00:00'),
('20-00002', 'Daniela', 'Cabrera', 'daniela.cabrera@student.edu', 'BS Information Technology', 'IT', 'Networking', 'NT', '4th Year', 'Available', 6, '2026-05-12 10:00:00'),
('20-00003', 'Ernesto', 'Campos', 'ernesto.campos@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Available', 6, '2026-05-12 10:00:00'),
('20-00004', 'Leticia', 'Dominguez', 'leticia.dominguez@student.edu', 'BS Information Technology', 'IT', 'Networking', 'NT', '4th Year', 'Available', 6, '2026-05-12 10:00:00'),
('20-00005', 'Guillermo', 'Ortega', 'guillermo.ortega@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'BA', '4th Year', 'Available', 6, '2026-05-12 10:00:00');

-- Assign all new students to adviser 8 (John Dave Briones)
INSERT INTO adviser_assignment (adviser_id, student_id, status)
SELECT 8, student_id, 'Active' FROM student 
WHERE student_number LIKE '2_-%' OR student_number LIKE '20-%';

SELECT 'Recreation complete! All students are 4th Year with BA/NT sections only.' as status;
SELECT COUNT(*) as total_students FROM student WHERE student_number LIKE '2_-%' OR student_number LIKE '20-%';
