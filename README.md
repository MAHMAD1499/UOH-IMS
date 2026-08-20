# Internship Management System (IMS) - University of Haripur (UOH)

A comprehensive web application designed to streamline, track, and manage student internships for the Department of Information Technology and Computer Science at the University of Haripur.

---

## 🚀 Key Features & Role Portals

### 1. Student Portal
* **Academic & Profile Details:** Students can view and update their profile and academic semester details.
* **Internship Reports:** Submit weekly/final internship reports with support for image/document attachment validations (JPG, PNG).
* **Internship Recommendation Letters:** View and download/print their official internship recommendation letter once approved by the Focal Person.
* **Grading & Feedback:** Real-time visibility into supervisor feedback and marks.

### 2. Focal Person Portal
* **Registered Students Directory:** View registered students and their details in a tabular layout (Roll No, Name, Father Name, Program, Semester, Session).
* **Add Student Popup Modal:** Register new students through an interactive popup modal at the top-right of the dashboard (no page redirects, clean UI validations).
* **Assign Faculty Supervisors:** Interactively assign/update academic supervisors to students using a Bootstrap-styled overlay.
* **Internship Letter Approvals:** Access, review, approve, or revoke internship letter drafts. The system restricts students from downloading the letter until approved.
* **Session Filtering:** Real-time client-side filter to quickly sort through records by academic session (Spring/Fall).

### 3. Faculty Supervisor Portal
* Manage and monitor assigned student records.
* Grade submitted weekly/final internship reports and provide comments.

---

## 🛠️ Technology Stack
* **Backend:** PHP (OOP & Procedural API using MySQLi parameterized prepared statements)
* **Frontend:** HTML5, Vanilla JavaScript, FontAwesome Icons
* **Styling:** CSS3 Custom Design System (Glassmorphism, gradients, responsive grids, custom modal framework)
* **Database:** MySQL / MariaDB

---

## 📦 Installation & Setup

1. **Prerequisites:**
   * Install XAMPP/WAMP or any PHP & MySQL development environment.
   * Make sure Apache and MySQL services are running.

2. **Clone / Copy Code:**
   * Place the repository code inside your server's root folder (e.g. `d:\xampp\htdocs\IMS\`).

3. **Database Configuration:**
   * Access phpMyAdmin (`http://localhost/phpmyadmin`) and create a new database named:
     ```sql
     internship management system
     ```
   * Import the [`seed_users.sql`](file:///d:/xampp/htdocs/IMS/seed_users.sql) file into the database:
     * This will reset the tables and insert mock users, profiles, and academic records (54 students, focal person, and supervisor).

4. **Run the App:**
   * Open your browser and navigate to `http://localhost/IMS/` or the configured local domain.

---

## 🔑 Test Credentials
All default seed users share the password: `password`. Students can also log in using their respective roll number as their password.

| Role | Username | Password |
| :--- | :--- | :--- |
| **Focal Person** | `F26-2345` | `password` |
| **Faculty Supervisor** | `FSP-0001` | `password` |
| **Student** | `S23-1234` | `password` |
| **Student** | `S23-1235` | `password` |
| **Student** | `S23-1222` | `password` |
