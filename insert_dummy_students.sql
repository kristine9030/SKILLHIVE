-- Insert dummy students assigned to different school years
-- Minimum 5 per school year

-- School Year 2024-2025 (id=1) - 6 students
INSERT INTO student (student_number, first_name, last_name, email, program, department, track, section, year_level, availability_status, school_year_id, archived_at) VALUES
('2024001', 'Juan', 'Cruz', 'juan.cruz@student.edu', 'BS Information Technology', 'IT', 'Software Development', 'A', '4th Year', 'Available', 1, NULL),
('2024002', 'Maria', 'Santos', 'maria.santos@student.edu', 'BS Information Technology', 'IT', 'Software Development', 'A', '4th Year', 'Currently Interning', 1, NULL),
('2024003', 'Pedro', 'Reyes', 'pedro.reyes@student.edu', 'BS Information Technology', 'IT', 'Networking', 'B', '3rd Year', 'Available', 1, NULL),
('2024004', 'Ana', 'Garcia', 'ana.garcia@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'C', '2nd Year', 'Unavailable', 1, NULL),
('2024005', 'Carlos', 'Lopez', 'carlos.lopez@student.edu', 'BS Information Technology', 'IT', 'Software Development', 'A', '3rd Year', 'Available', 1, NULL),
('2024006', 'Rosa', 'Fernandez', 'rosa.fernandez@student.edu', 'BS Information Technology', 'IT', 'Networking', 'B', '4th Year', 'Currently Interning', 1, NULL);

-- School Year 2025-2026 (id=2 - Active) - 7 students
INSERT INTO student (student_number, first_name, last_name, email, program, department, track, section, year_level, availability_status, school_year_id, archived_at) VALUES
('2025001', 'Miguel', 'Tanque', 'miguel.tanque@student.edu', 'BS Information Technology', 'IT', 'Software Development', 'A', '3rd Year', 'Available', 2, NULL),
('2025002', 'Liza', 'Diaz', 'liza.diaz@student.edu', 'BS Information Technology', 'IT', 'Software Development', 'A', '2nd Year', 'Available', 2, NULL),
('2025003', 'Diego', 'Morales', 'diego.morales@student.edu', 'BS Information Technology', 'IT', 'Networking', 'B', '3rd Year', 'Currently Interning', 2, NULL),
('2025004', 'Sofia', 'Ramirez', 'sofia.ramirez@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'C', '2nd Year', 'Available', 2, NULL),
('2025005', 'Roberto', 'Castro', 'roberto.castro@student.edu', 'BS Information Technology', 'IT', 'Software Development', 'A', '4th Year', 'Unavailable', 2, NULL),
('2025006', 'Elena', 'Valdez', 'elena.valdez@student.edu', 'BS Information Technology', 'IT', 'Networking', 'B', '2nd Year', 'Available', 2, NULL),
('2025007', 'Lucas', 'Jimenez', 'lucas.jimenez@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'C', '3rd Year', 'Currently Interning', 2, NULL);

-- School Year 2026-2027 (id=3 - Archived) - 6 students
INSERT INTO student (student_number, first_name, last_name, email, program, department, track, section, year_level, availability_status, school_year_id, archived_at) VALUES
('2026001', 'Fernando', 'Alonso', 'fernando.alonso@student.edu', 'BS Information Technology', 'IT', 'Software Development', 'A', '4th Year', 'Available', 3, '2026-05-12 10:00:00'),
('2026002', 'Isabella', 'Montero', 'isabella.montero@student.edu', 'BS Information Technology', 'IT', 'Software Development', 'A', '3rd Year', 'Currently Interning', 3, NULL),
('2026003', 'Adrian', 'Perez', 'adrian.perez@student.edu', 'BS Information Technology', 'IT', 'Networking', 'B', '4th Year', 'Available', 3, '2026-05-12 10:00:00'),
('2026004', 'Valentina', 'Herrera', 'valentina.herrera@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'C', '2nd Year', 'Available', 3, NULL),
('2026005', 'Marco', 'Gutierrez', 'marco.gutierrez@student.edu', 'BS Information Technology', 'IT', 'Software Development', 'A', '3rd Year', 'Unavailable', 3, NULL),
('2026006', 'Gabriela', 'Rios', 'gabriela.rios@student.edu', 'BS Information Technology', 'IT', 'Networking', 'B', '2nd Year', 'Available', 3, NULL);

-- School Year 2027-2028 (id=4 - Archived) - 6 students
INSERT INTO student (student_number, first_name, last_name, email, program, department, track, section, year_level, availability_status, school_year_id, archived_at) VALUES
('2027001', 'Santiago', 'Moreno', 'santiago.moreno@student.edu', 'BS Information Technology', 'IT', 'Software Development', 'A', '3rd Year', 'Available', 4, '2026-05-12 10:00:00'),
('2027002', 'Catalina', 'Flores', 'catalina.flores@student.edu', 'BS Information Technology', 'IT', 'Software Development', 'A', '4th Year', 'Currently Interning', 4, '2026-05-12 10:00:00'),
('2027003', 'Javier', 'Soto', 'javier.soto@student.edu', 'BS Information Technology', 'IT', 'Networking', 'B', '2nd Year', 'Available', 4, NULL),
('2027004', 'Veronica', 'Ruiz', 'veronica.ruiz@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'C', '3rd Year', 'Available', 4, NULL),
('2027005', 'Francisco', 'Medina', 'francisco.medina@student.edu', 'BS Information Technology', 'IT', 'Software Development', 'A', '4th Year', 'Unavailable', 4, '2026-05-12 10:00:00'),
('2027006', 'Alejandra', 'Vargas', 'alejandra.vargas@student.edu', 'BS Information Technology', 'IT', 'Networking', 'B', '3rd Year', 'Available', 4, NULL);

-- School Year 2028-2029 (id=5 - Archived) - 6 students
INSERT INTO student (student_number, first_name, last_name, email, program, department, track, section, year_level, availability_status, school_year_id, archived_at) VALUES
('2028001', 'Mateo', 'Acosta', 'mateo.acosta@student.edu', 'BS Information Technology', 'IT', 'Software Development', 'A', '2nd Year', 'Available', 5, NULL),
('2028002', 'Martina', 'Nava', 'martina.nava@student.edu', 'BS Information Technology', 'IT', 'Software Development', 'A', '3rd Year', 'Currently Interning', 5, NULL),
('2028003', 'Antonio', 'Rojas', 'antonio.rojas@student.edu', 'BS Information Technology', 'IT', 'Networking', 'B', '2nd Year', 'Available', 5, '2026-05-12 10:00:00'),
('2028004', 'Claudia', 'Rivas', 'claudia.rivas@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'C', '4th Year', 'Available', 5, '2026-05-12 10:00:00'),
('2028005', 'Ricardo', 'Palacios', 'ricardo.palacios@student.edu', 'BS Information Technology', 'IT', 'Software Development', 'A', '3rd Year', 'Unavailable', 5, NULL),
('2028006', 'Pamela', 'Iglesias', 'pamela.iglesias@student.edu', 'BS Information Technology', 'IT', 'Networking', 'B', '4th Year', 'Available', 5, NULL);

-- School Year 2020-2021 (id=6 - Archived) - 5 students (Alumni)
INSERT INTO student (student_number, first_name, last_name, email, program, department, track, section, year_level, availability_status, school_year_id, archived_at) VALUES
('2020001', 'Rodrigo', 'Muniz', 'rodrigo.muniz@student.edu', 'BS Information Technology', 'IT', 'Software Development', 'A', '4th Year', 'Available', 6, '2026-05-12 10:00:00'),
('2020002', 'Daniela', 'Cabrera', 'daniela.cabrera@student.edu', 'BS Information Technology', 'IT', 'Software Development', 'A', '4th Year', 'Available', 6, '2026-05-12 10:00:00'),
('2020003', 'Ernesto', 'Campos', 'ernesto.campos@student.edu', 'BS Information Technology', 'IT', 'Networking', 'B', '4th Year', 'Available', 6, '2026-05-12 10:00:00'),
('2020004', 'Leticia', 'Dominguez', 'leticia.dominguez@student.edu', 'BS Information Technology', 'IT', 'Business Analytics', 'C', '4th Year', 'Available', 6, '2026-05-12 10:00:00'),
('2020005', 'Guillermo', 'Ortega', 'guillermo.ortega@student.edu', 'BS Information Technology', 'IT', 'Software Development', 'A', '4th Year', 'Available', 6, '2026-05-12 10:00:00');
