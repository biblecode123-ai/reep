-- Seeder for Colleges and Departments of Madda Walabu University
-- Generated based on provided data

PRAGMA foreign_keys = ON;

-- Insert Colleges
INSERT INTO colleges (name, code) VALUES 
('College of Agriculture and Natural Resources', 'COANR'),
('College of Business and Economics', 'COBE'),
('College of Engineering', 'COE'),
('College of Computing', 'COC'),
('College of Natural and Computational Sciences', 'CONCS'),
('College of Social Sciences and Humanities', 'COSSH'),
('College of Education and Behavioral Sciences', 'COEBS'),
('School of Health Sciences', 'SOHS'),
('School of Medicine', 'SOM'),
('School of Law', 'SOL');

-- Insert Departments
INSERT INTO departments (college_id, name, code) VALUES 
-- COANR
((SELECT id FROM colleges WHERE code='COANR'), 'Agricultural Economics', 'AE-COANR'),
((SELECT id FROM colleges WHERE code='COANR'), 'Animal and Range Sciences', 'ARS-COANR'),
((SELECT id FROM colleges WHERE code='COANR'), 'Plant Sciences', 'PS-COANR'),
((SELECT id FROM colleges WHERE code='COANR'), 'Natural Resources Management', 'NRM-COANR'),
((SELECT id FROM colleges WHERE code='COANR'), 'Forestry', 'F-COANR'),
((SELECT id FROM colleges WHERE code='COANR'), 'Rural Development and Agricultural Extension', 'RDAE-COANR'),

-- COBE
((SELECT id FROM colleges WHERE code='COBE'), 'Accounting and Finance', 'AF-COBE'),
((SELECT id FROM colleges WHERE code='COBE'), 'Economics', 'E-COBE'),
((SELECT id FROM colleges WHERE code='COBE'), 'Management', 'M-COBE'),
((SELECT id FROM colleges WHERE code='COBE'), 'Marketing Management', 'MM-COBE'),
((SELECT id FROM colleges WHERE code='COBE'), 'Tourism Management', 'TM-COBE'),

-- COE
((SELECT id FROM colleges WHERE code='COE'), 'Civil Engineering', 'CE-COE'),
((SELECT id FROM colleges WHERE code='COE'), 'Electrical and Computer Engineering', 'ECE-COE'),
((SELECT id FROM colleges WHERE code='COE'), 'Mechanical Engineering', 'ME-COE'),
((SELECT id FROM colleges WHERE code='COE'), 'Construction Technology and Management', 'CTM-COE'),
((SELECT id FROM colleges WHERE code='COE'), 'Water Resources and Irrigation Engineering', 'WRIE-COE'),
((SELECT id FROM colleges WHERE code='COE'), 'Surveying Engineering', 'SE-COE'),
((SELECT id FROM colleges WHERE code='COE'), 'Architecture', 'A-COE'),

-- COC
((SELECT id FROM colleges WHERE code='COC'), 'Computer Science', 'CS-COC'),
((SELECT id FROM colleges WHERE code='COC'), 'Information Technology', 'IT-COC'),
((SELECT id FROM colleges WHERE code='COC'), 'Information Systems', 'IS-COC'),
((SELECT id FROM colleges WHERE code='COC'), 'Information Science', 'ISC-COC'),

-- CONCS
((SELECT id FROM colleges WHERE code='CONCS'), 'Biology', 'B-CONCS'),
((SELECT id FROM colleges WHERE code='CONCS'), 'Chemistry', 'C-CONCS'),
((SELECT id FROM colleges WHERE code='CONCS'), 'Physics', 'P-CONCS'),
((SELECT id FROM colleges WHERE code='CONCS'), 'Mathematics', 'M-CONCS'),
((SELECT id FROM colleges WHERE code='CONCS'), 'Statistics', 'S-CONCS'),
((SELECT id FROM colleges WHERE code='CONCS'), 'Sport Science', 'SS-CONCS'),

-- COSSH
((SELECT id FROM colleges WHERE code='COSSH'), 'Afaan Oromo Language and Literature', 'AOLL-COSSH'),
((SELECT id FROM colleges WHERE code='COSSH'), 'Amharic Language and Literature', 'ALL-COSSH'),
((SELECT id FROM colleges WHERE code='COSSH'), 'English Language and Literature', 'ELL-COSSH'),
((SELECT id FROM colleges WHERE code='COSSH'), 'Civics and Ethical Studies', 'CES-COSSH'),
((SELECT id FROM colleges WHERE code='COSSH'), 'Geography and Environmental Studies', 'GES-COSSH'),
((SELECT id FROM colleges WHERE code='COSSH'), 'History and Heritage Management', 'HHM-COSSH'),
((SELECT id FROM colleges WHERE code='COSSH'), 'Journalism and Communication', 'JC-COSSH'),
((SELECT id FROM colleges WHERE code='COSSH'), 'Sociology', 'S-COSSH'),

-- COEBS
((SELECT id FROM colleges WHERE code='COEBS'), 'Psychology', 'P-COEBS'),
((SELECT id FROM colleges WHERE code='COEBS'), 'Educational Planning and Management', 'EPAM-COEBS'),
((SELECT id FROM colleges WHERE code='COEBS'), 'Adult Education', 'AE-COEBS'),
((SELECT id FROM colleges WHERE code='COEBS'), 'Early Childhood Education', 'ECE-COEBS'),

-- SOHS
((SELECT id FROM colleges WHERE code='SOHS'), 'Nursing', 'N-SOHS'),
((SELECT id FROM colleges WHERE code='SOHS'), 'Midwifery', 'M-SOHS'),
((SELECT id FROM colleges WHERE code='SOHS'), 'Public Health', 'PH-SOHS'),
((SELECT id FROM colleges WHERE code='SOHS'), 'Medical Laboratory Science', 'MLS-SOHS'),

-- SOM
((SELECT id FROM colleges WHERE code='SOM'), 'Medicine', 'M-SOM'),

-- SOL
((SELECT id FROM colleges WHERE code='SOL'), 'Law', 'L-SOL');
