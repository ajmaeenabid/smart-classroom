# SmartClass LMS — Advanced V3.8
## Complete Project Report

---

# 1. Introduction

## 1.1 Project Overview

**SmartClass LMS** (Learning Management System) is a comprehensive, role-based web application designed to facilitate digital classroom management for teachers, students, and guardians. Built with PHP, MySQL, and modern frontend technologies, it provides a complete ecosystem for creating and managing virtual classrooms, distributing educational content, conducting assessments, tracking attendance, and analyzing student performance.

## 1.2 Problem Statement

Traditional classroom management relies on fragmented tools — email for announcements, paper for attendance, separate platforms for quizzes and grading. SmartClass LMS consolidates all these functions into a single, unified platform, eliminating the need for multiple disconnected tools and providing real-time visibility into student progress for all stakeholders.

## 1.3 Objectives

- Provide a centralized platform for classroom management with role-based access (Teacher, Student, Guardian)
- Enable real-time communication through announcements, messaging, and notifications
- Automate attendance tracking via QR code generation and scanning
- Facilitate assessment through quizzes, polls, and assignments with grading workflows
- Offer comprehensive analytics and performance tracking for data-driven education
- Support offline submission workflows for students without reliable internet access
- Enable bulk content distribution across multiple classrooms simultaneously

## 1.4 Technology Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | PHP 8.x (Procedural + PDO) |
| **Database** | MySQL 8.x (utf8mb4) |
| **Frontend** | HTML5, CSS3, JavaScript (ES6+) |
| **CSS Framework** | Custom CSS with CSS Variables (Dark theme) |
| **Icons** | Font Awesome 6.5 |
| **Fonts** | Google Fonts (Inter) |
| **Web Server** | Apache (XAMPP) |
| **AI Integration** | OpenRouter AI API (NVIDIA Nemotron model) |
| **QR Code** | QR Server API (api.qrserver.com) |
| **Video Meet** | External link integration (Google Meet, Zoom, etc.) |
| **Charts** | Chart.js (via CDN) |

## 1.5 Project Structure

```
advance_classroom/
├── index.php                    # Login / Register page
├── config/
│   ├── db.php                   # Database connection & helper functions
│   └── layout.php               # Shared layout (sidebar, topbar, modals)
├── dashboard/
│   ├── teacher.php              # Teacher dashboard
│   ├── student.php              # Student dashboard
│   └── guardian.php             # Guardian dashboard
├── classroom/
│   ├── index.php                # Classroom hub (Stream, Classwork, People, Grades, Materials)
│   ├── attendance.php           # QR Attendance system
│   ├── quiz.php                 # Quiz & Poll system
│   ├── grades.php               # Grade management & submission review
│   ├── video_meet.php           # Video Meet session management
│   ├── archive.php              # Archive & personal storage viewer
│   └── qr_scan.php              # QR code scan landing page
├── api/
│   ├── auth.php                 # Authentication API
│   ├── classes.php              # Class CRUD & material upload
│   ├── assignments.php          # Assignment create/submit/grade
│   ├── quiz.php                 # Quiz & Poll API
│   ├── qr_attendance.php        # QR attendance session management
│   ├── archive.php              # Archive & personal storage API
│   ├── material_transfer.php    # Material import/transfer between classes
│   ├── search.php               # Global search suggestions API
│   ├── notifications.php        # Notification management API
│   └── ai_chat.php              # AI Chatbot integration (OpenRouter)
├── analytics/
│   └── performance.php          # Student performance analytics dashboard
├── global/
│   ├── calendar.php             # Calendar with events & assignment due dates
│   ├── messages.php             # Direct messaging system
│   ├── notifications.php        # Notification center
│   ├── profile.php              # User profile management
│   └── export.php               # CSV/Excel export for grades
├── guardian/
│   ├── link.php                 # Link student accounts to guardian
│   ├── records.php              # View linked student records
│   └── download.php             # Download student materials
├── offline/
│   └── submission.php           # Offline assignment submission workflow
├── assets/
│   ├── css/style.css            # Main stylesheet (dark theme)
│   └── js/main.js              # Core JavaScript (SCS utility library)
├── database/
│   ├── schema.sql               # Full database schema
│   └── update_v4.0.sql          # v4.0 migration (archive + QR tables)
└── uploads/                     # File upload directory
```

---

# 2. Related Work / All Functionality

## 2.1 Authentication & User Management

### 2.1.1 Registration & Login
- **Three user roles**: Teacher, Student, Guardian — selected during registration
- Email-based registration with password hashing (`bcrypt` via `password_hash()`)
- Email uniqueness validation
- Session-based authentication using PHP sessions
- Automatic role-based redirect after login (teacher → teacher dashboard, student → student dashboard, guardian → guardian dashboard)
- Demo accounts pre-loaded: `teacher@demo.com`, `student@demo.com`, `guardian@demo.com` (password: `password123`)

### 2.1.2 Profile Management
- Edit name, email, bio, phone number
- Avatar upload support
- Password change functionality
- Profile updates reflected in session immediately

### 2.1.3 Session Management
- PHP native sessions with `session_start()`
- `$_SESSION['user']` stores complete user record
- `$_SESSION['user_id']` for quick identity checks
- Logout via `api/auth.php?action=logout` destroys session
- **Known limitation**: Shared browser cookies mean logging into a different account in another tab overwrites the current session

---

## 2.2 Dashboard System

### 2.2.1 Teacher Dashboard
- **Statistics cards**: Total classes, total students across all classes, total assignments, pending submissions to grade
- **Class cards**: Visual grid of all active classes with member count, cover color, and quick-access links
- **Recent Submissions**: Table of latest student submissions with student name, assignment title, class, and timestamp
- **Upcoming Deadlines**: List of assignments due soon across all classes
- **Grade Analytics Chart**: Bar chart showing average grade per class (Chart.js)
- **Quick Actions**: Create new class button

### 2.2.2 Student Dashboard
- **Statistics cards**: Enrolled classes count, pending assignments, average grade, attendance rate, quiz average
- **My Classes grid**: Visual cards for each enrolled class with teacher name and member count
- **Pending Assignments**: List of unsubmitted assignments with due dates and class info
- **Activity Timeline**: Combined feed of recent announcements and new assignments across all classes
- **Performance Chart**: Per-class grade comparison chart

### 2.2.3 Guardian Dashboard
- **Linked Students**: List of approved student links with their stats
- **Per-student overview**: Class count, average grade, attendance rate for each linked student
- **Pending Link Requests**: Approve/reject student link requests
- **Link New Student**: Search and link to a student account
- **Quick Access**: View detailed records and download materials for linked students

---

## 2.3 Classroom Hub

The central workspace for each class, accessible via `classroom/index.php?id={classId}&tab={tab}`.

### 2.3.1 Stream Tab (Announcements)
- **Teacher**: Post announcements with optional file attachments
- **Send to All My Classrooms**: Checkbox option to broadcast the same announcement to every class the teacher owns simultaneously
- **Announcement feed**: Chronological list with author avatar, name, timestamp, content, and attachment links
- **Delete announcements**: Dropdown menu with delete option (teacher only)
- **Comment placeholder**: UI for future comment feature

### 2.3.2 Classwork Tab (Assignments)
- **Teacher**: Create assignments with title, instructions, due date/time, total points, and optional file attachment
- **Offline submission toggle**: Allow/disable QR-based offline submission for each assignment
- **Assignment list**: Cards showing title, due date, points, submission count (teacher) or submission status (student)
- **Student view**: Submit assignments via file upload or text content
- **Late detection**: Automatically marks submissions as "late" if past due date
- **Duplicate prevention**: Students cannot resubmit after first submission

### 2.3.3 People Tab
- **Teacher info card**: Name, email displayed prominently
- **Student roster**: Complete list of enrolled students with email and direct message link

### 2.3.4 Grades Tab
- **Teacher view**: Table of all students with average grade, graded count, performance bar, and link to detailed analytics
- **Student view**: Personal grade table showing each assignment's grade, status, feedback, and submission time
- **CSV Export**: Download grade data as CSV file

### 2.3.5 Materials Tab
- **Teacher**: Add materials (File Upload, External Link, or Video Link)
- **Send to All My Classrooms**: Checkbox to add the same material to every class the teacher owns
- **Import from Past Course**: Modal to browse and select materials from other active classes taught by the same teacher, with search/filter and bulk select
- **Material cards**: Title, description, file type icon, preview button (images, videos, PDFs), download button
- **Live Preview**: In-modal preview for images, videos, and PDFs before download
- **Student**: Save all materials to personal archive (for archived classes)

---

## 2.4 QR Attendance System

### 2.4.1 Teacher Side
- **Generate QR Code**: Select duration (1, 5, 10, 15, 30 minutes or custom) and generate a time-limited QR code
- **QR Code Display**: Large, scannable QR code rendered via `api.qrserver.com` with countdown timer
- **Manual Token**: Display the attendance token for students who can't scan
- **Stop Session**: Manually expire the QR session before timer runs out
- **Live Attendance List**: Real-time list of students who have checked in, auto-refreshing every 3 seconds
- **Attendance Stats**: Present, absent, late counts for the selected date
- **Date Selector**: View attendance records for any past date
- **Manual Marking**: Mark students present/absent/late/excused manually

### 2.4.2 Student Side
- **Active QR Detection**: Auto-polling every 3 seconds to detect when teacher generates a QR code
- **QR Code Display**: Students can see the QR code image directly on their screen
- **Token Display**: The attendance token is shown as a code for manual entry
- **Manual Check-in**: Enter the token or scan the QR code URL to mark attendance
- **Camera Scan**: Use device camera to scan the QR code directly
- **Attendance Stats**: Personal attendance rate, days present, absent, late
- **Attendance History**: View past attendance records

### 2.4.3 QR Session Lifecycle
1. Teacher clicks "Generate QR" → API creates session with unique token and expiration timestamp
2. QR code image generated via external API encoding the scan URL
3. Student polls `api/qr_attendance.php?action=status` every 3 seconds
4. When active session detected, student sees QR image, token, and countdown
5. Student scans QR or enters token → API validates enrollment, checks duplicates, marks attendance
6. Session auto-expires when `expires_at < NOW()` or teacher manually stops it

---

## 2.5 Quiz & Poll System

### 2.5.1 Quiz Creation (Teacher)
- Create quiz with title, description, and time limit (minutes)
- Add multiple MCQ questions with options and correct answer
- Set points per question
- Save as Draft or publish as Live
- Live quizzes trigger student notifications

### 2.5.2 Quiz Taking (Student)
- View available quizzes (live/draft status)
- Answer MCQ questions within time limit
- Auto-grading on submission: compare answers against correct answers, calculate score
- View results immediately after submission
- One attempt per quiz (duplicate prevention)

### 2.5.3 Quiz Management (Teacher)
- Toggle quiz status: Draft ↔ Live ↔ Closed
- View student responses and scores
- Real-time results for live quizzes

### 2.5.4 Poll System
- Create single-question polls with multiple options
- Students vote once (duplicate vote prevention)
- Real-time vote distribution display
- Visual bar chart of results

---

## 2.6 Assignment & Grading Workflow

### 2.6.1 Assignment Lifecycle
1. **Teacher creates** assignment with title, description, due date, points, optional attachment
2. **Students notified** via notification system
3. **Student submits** file upload or text content before deadline
4. **Late submissions** automatically flagged
5. **Teacher grades** with numeric score and optional feedback
6. **Student notified** of grade and feedback
7. **Grade appears** in Grades tab and Performance Analytics

### 2.6.2 Grading Features
- Numeric grade entry (0–100+)
- Written feedback per submission
- Status tracking: submitted → graded / late / missing
- Bulk view of all submissions per assignment
- Grade export to CSV

---

## 2.7 Video Meet Integration

- **Teacher creates** meet session with title, external meet link (Google Meet, Zoom, etc.), and optional schedule
- **Students notified** when meet is scheduled
- **Session list**: All past and upcoming meets for the class
- **Status management**: Scheduled → Live → Ended
- **One-click join**: Direct link to external video platform
- **Meet history**: Record of all past sessions

---

## 2.8 Calendar System

- **Monthly calendar view** with navigation (prev/next month)
- **Custom events**: Create personal events with title, description, date, and type (assignment, quiz, meet, event, holiday)
- **Auto-populated**: Assignment due dates automatically appear on the calendar
- **Event types**: Color-coded by type (assignment, quiz, meet, custom event, holiday)
- **Per-user**: Each user sees only their own events and relevant class assignments

---

## 2.9 Notification System

- **In-app notifications**: Bell icon in topbar with unread count badge
- **Notification types**: info, success, warning, error — each with distinct color
- **Auto-generated notifications** for:
  - New assignment created
  - Assignment graded
  - New student joined class
  - Live quiz started
  - Video meet scheduled
  - Class archived/restored
  - QR attendance session
- **Mark as read**: Individual or bulk mark-all-as-read
- **Notification center**: Dedicated page to view all notifications with links to relevant pages

---

## 2.10 Search System

- **Global search bar** in topbar with real-time suggestions
- **Searches across**: Assignments, Materials, Quizzes, Announcements, Students (teacher only)
- **Role-filtered**: Students only see results from their enrolled classes; teachers see all their classes
- **Click-to-navigate**: Selecting a result navigates directly to the item (isolated view)

---

## 2.11 AI Chatbot

- **Integrated chat widget** accessible from any page
- **Context-aware**: AI receives user name, role, enrolled classes, grade averages, attendance rate as context
- **Powered by OpenRouter AI** using NVIDIA Nemotron model (free tier)
- **OpenAI-compatible API** format for easy model switching
- **Conversational**: Maintains chat history within session
- **Error handling**: Graceful error messages for API failures
- **Smart responses**: Answers questions about classes, grades, deadlines, attendance, and general academic guidance

---

## 2.12 Archive & Personal Storage

### 2.12.1 Class Archival (Teacher)
- **Soft-delete**: Archive a class instead of permanent deletion
- **48-hour grace period**: Class remains accessible (read-only) for 2 days after archiving
- **Restore option**: Teacher can restore archived class within grace period
- **Auto-purge**: Expired archives are permanently deleted (CASCADE removes all related data)
- **Student notification**: All enrolled students are notified when a class is archived

### 2.12.2 Student Personal Archive
- **Save All Materials**: One-click save of all class materials to personal archive
- **Folder organization**: Materials saved in folders named after the source class
- **File duplication**: Actual files are copied (not just referenced) for persistence
- **Browse & download**: View and download saved materials anytime
- **Delete folders**: Remove personal archive folders when no longer needed

### 2.12.3 Material Import/Transfer
- **Import from Past Course**: Browse materials from other active classes
- **Search & filter**: Find specific materials by title or type
- **Bulk select**: Select multiple materials with "Select All" option
- **Copy (not move)**: Materials are duplicated into the target class, originals remain intact
- **Active-only**: Only materials from active classes can be transferred

---

## 2.13 Offline Assignment Submission

- **For students without reliable internet**: Generate a QR code token for each assignment
- **Workflow**:
  1. Student selects an offline-allowed assignment
  2. System generates a unique token and QR code
  3. Student shows QR code or token to teacher offline
  4. Teacher scans/verifies the token via the verification page
  5. Submission is marked as verified/offline
- **Token management**: View all generated tokens and their status (pending/submitted/verified)
- **Teacher verification**: Enter token to validate and mark submission

---

## 2.14 Performance Analytics

- **Per-student dashboard** with comprehensive metrics
- **Class-wise breakdown**: Grade average, attendance rate, quiz average per class
- **Assignment grade chart**: Visual chart of grades across all assignments
- **Attendance trend**: Visual representation of attendance over time
- **Guardian access**: Guardians can view analytics for their linked students
- **Teacher access**: Teachers can view any student's analytics from the Grades tab
- **Performance cache**: Pre-computed analytics stored in `performance_cache` table for efficiency

---

## 2.15 Messaging System

- **Direct messages**: Send messages to any user (teacher ↔ student, student ↔ student)
- **Message thread view**: Conversation-style display
- **Attachment support**: Send files with messages
- **Class chat**: Messages scoped to a specific class

---

## 2.16 Guardian Linking System

- **Link request**: Guardian sends link request to a student by email
- **Approval workflow**: Student approves or rejects the link
- **Status tracking**: Pending → Approved / Rejected
- **Access control**: Only approved guardians can view student data
- **Multi-student**: One guardian can link to multiple students
- **Records view**: Guardians see class list, grades, and attendance for linked students
- **Material download**: Guardians can download materials from linked students' classes

---

## 2.17 Send to All Classrooms (Bulk Broadcast)

- **Announcements**: Checkbox "Send to All My Classrooms" in the Stream post form
- **Materials**: Checkbox "Send to All My Classrooms" in the Add Material modal
- **When checked**: The content is inserted into ALL active classes owned by the teacher
- **File sharing**: Same uploaded file is referenced across all classes (no duplicate uploads)
- **Use case**: Posting "Tomorrow's class is canceled" or uploading a shared slide deck to every class at once

---

# 3. Design

## 3.1 Architecture

SmartClass LMS follows a **server-side rendered (SSR)** architecture with **progressive enhancement** via JavaScript:

```
┌─────────────────────────────────────────────────┐
│                   Browser                        │
│  ┌──────────┐  ┌──────────┐  ┌───────────────┐ │
│  │  HTML/CSS │  │   JS     │  │  Fetch/AJAX   │ │
│  │ (Rendered)│  │ (SCS lib)│  │  (API calls)  │ │
│  └─────┬─────┘  └────┬─────┘  └──────┬────────┘ │
└────────┼──────────────┼───────────────┼──────────┘
         │              │               │
┌────────▼──────────────▼───────────────▼──────────┐
│              Apache / PHP                         │
│  ┌──────────┐  ┌──────────┐  ┌───────────────┐  │
│  │  Pages   │  │  Layout  │  │   API Layer   │  │
│  │  (*.php) │  │ (shared) │  │  (api/*.php)  │  │
│  └─────┬────┘  └────┬─────┘  └──────┬────────┘  │
│        │             │               │            │
│  ┌─────▼─────────────▼───────────────▼────────┐  │
│  │           config/db.php (PDO)               │  │
│  └──────────────────┬─────────────────────────┘  │
└─────────────────────┼────────────────────────────┘
                      │
┌─────────────────────▼────────────────────────────┐
│              MySQL Database                       │
│           (smart_classroom)                       │
└──────────────────────────────────────────────────┘
```

### Key Design Decisions:
- **No frontend framework**: Pure PHP rendering with JavaScript enhancements
- **API pattern**: RESTful-ish endpoints under `api/` returning JSON or performing redirects
- **Shared layout**: `config/layout.php` provides `renderHead()`, `renderSidebar()`, `renderTopbar()`, `renderFooter()`
- **SCS JavaScript library**: `assets/js/main.js` provides `SCS.apiRequest()`, `SCS.showToast()`, `SCS.confirmAction()`, `SCS.copyText()`, `SCS.setLoading()`
- **Modal system**: CSS-based modal overlays toggled via `openModal()`/`closeModal()`

## 3.2 Database Schema

### Entity Relationship Overview

```
users ──┬──< class_members >── classes
        │                    │
        ├──< announcements   ├──< assignments ──< submissions
        │                    │
        ├──< attendance      ├──< materials
        │                    │
        ├──< quiz_responses  ├──< quizzes ──< quiz_questions
        │                    │
        ├──< poll_votes      ├──< polls
        │                    │
        ├──< messages        ├──< meet_sessions
        │                    │
        ├──< notifications   ├──< calendar_events
        │                    │
        ├──< guardian_links  ├──< qr_attendance_sessions
        │                    │
        ├──< direct_messages ├──< offline_tokens
        │                    │
        └──< student_archive ──< student_archive_items
                              │
                              └──< archived_classes
                              
        performance_cache (student_id, class_id)
```

### Complete Table Reference (22 tables)

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `users` | All user accounts | id, name, email, password (bcrypt), role (enum) |
| `classes` | Classroom entities | id, name, section, subject, room, code (unique 7-char), teacher_id, cover_color, status (active/archived) |
| `class_members` | Student-class enrollment | class_id, user_id (unique pair) |
| `announcements` | Stream posts | class_id, author_id, content, attachment |
| `announcement_comments` | Comments on posts | announcement_id, author_id, content |
| `assignments` | Class assignments | class_id, title, description, due_date, points, attachment, allow_offline |
| `submissions` | Student submissions | assignment_id, student_id, file_path, grade, feedback, status, is_offline |
| `materials` | Class resources | class_id, title, description, file_path, link_url, type (file/link/video) |
| `attendance` | Attendance records | class_id, student_id, date, status (present/absent/late/excused) |
| `qr_attendance_sessions` | QR attendance sessions | class_id, teacher_id, date, token (unique), is_active, expires_at |
| `quizzes` | Quiz containers | class_id, title, time_limit, status (draft/live/closed), is_live |
| `quiz_questions` | Quiz questions | quiz_id, question, type (mcq/true_false/short), options (JSON), correct_answer, points |
| `quiz_responses` | Student quiz answers | quiz_id, student_id, answers (JSON), score, total_points |
| `polls` | Quick polls | class_id, question, options (JSON), is_active |
| `poll_votes` | Poll responses | poll_id, student_id, option_index (unique pair) |
| `messages` | Class chat messages | class_id, sender_id, content, attachment |
| `direct_messages` | DM between users | sender_id, receiver_id, content, is_read |
| `notifications` | User notifications | user_id, title, message, type, is_read, link |
| `guardian_links` | Guardian-student links | guardian_id, student_id, status (pending/approved/rejected) |
| `meet_sessions` | Video meet sessions | class_id, title, meet_link, scheduled_at, status |
| `calendar_events` | Calendar entries | user_id, class_id, title, event_date, type |
| `offline_tokens` | Offline submission tokens | assignment_id, student_id, token, qr_code_path, status |
| `performance_cache` | Cached analytics | student_id, class_id, avg_grade, attendance_rate, quiz_avg |
| `archived_classes` | Archived class records | original_class_id, name, code, archived_at, delete_after |
| `student_archive` | Student saved folders | student_id, folder_name, source_class_id |
| `student_archive_items` | Saved material items | archive_id, title, file_path, link_url, type |

## 3.3 Security Design

- **Password hashing**: `password_hash()` with `PASSWORD_DEFAULT` (bcrypt)
- **SQL injection prevention**: All queries use PDO prepared statements
- **XSS prevention**: `htmlspecialchars()` via `e()` helper for all user-generated output
- **Access control**: Every API endpoint validates user role and ownership before executing
- **Session-based auth**: PHP native sessions with `session_start()`
- **File upload safety**: Extension validation, unique filenames via `uniqid()`
- **Enrollment verification**: APIs check `class_members` before returning class data

## 3.4 Key Workflows

### 3.4.1 Class Creation & Join Flow
```
Teacher → Dashboard → "Create Class" → Form (name, section, subject, room, color)
    → API generates unique 7-char code → INSERT into classes
    → Redirect to classroom page

Student → Dashboard → "Join Class" → Enter class code
    → API validates code → Check capacity → INSERT into class_members
    → Notify teacher → Redirect to classroom
```

### 3.4.2 Assignment Lifecycle
```
Teacher creates → INSERT assignments → Notify all class members
Student submits → INSERT submissions (check deadline for late flag) → Notify teacher
Teacher grades → UPDATE submissions (grade, feedback, status='graded') → Notify student
Student views → SELECT from submissions with grade & feedback
```

### 3.4.3 QR Attendance Flow
```
Teacher: Select duration → Generate QR
    → INSERT qr_attendance_sessions (token, expires_at = NOW() + duration)
    → Return QR image URL (api.qrserver.com encoding scan URL)

Student: Auto-poll every 3s → GET status API
    → If active: Display QR image, token, countdown
    → Scan/enter token → GET scan API
    → Validate enrollment + check duplicate → INSERT attendance → Show success

Expiry: expires_at < NOW() → is_active=0 → Session ends
```

### 3.4.4 Archive Flow
```
Teacher: "Archive Class" → UPDATE classes SET status='archived'
    → INSERT archived_classes (delete_after = NOW() + 48h)
    → Notify all students

Within 48h: Teacher can "Restore" → UPDATE status='active', DELETE from archived_classes
After 48h: Cron/purge → DELETE from classes (CASCADE removes all related data)

Student: "Save All Materials" → INSERT student_archive folder
    → For each material: Copy file, INSERT student_archive_items
```

---

# 4. User Manual

## 4.1 Installation

### Prerequisites
- XAMPP (Apache + MySQL + PHP) installed on Windows
- PHP 8.0+ with PDO MySQL extension enabled
- Web browser (Chrome, Firefox, Edge recommended)

### Step-by-Step Installation

1. **Copy project files**
   ```
   Copy the `advance_classroom` folder to: C:\xampp\htdocs\advance_classroom
   ```

2. **Start XAMPP services**
   - Open XAMPP Control Panel
   - Start **Apache** and **MySQL**

3. **Create the database**
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Create a new database named `smart_classroom`
   - Select utf8mb4_unicode_ci collation

4. **Import the schema**
   - Go to Import tab in phpMyAdmin
   - Select `database/schema.sql` from the project folder
   - Click "Go" to execute
   - For existing installations upgrading to v4.0, also import `database/update_v4.0.sql`

5. **Configure database connection** (if needed)
   - Open `config/db.php`
   - Default settings: host=`localhost`, user=`root`, pass=`` (empty), db=`smart_classroom`
   - Update if your MySQL configuration differs

6. **Configure base URL** (if needed)
   - In `config/db.php`, line 9: `define('BASE_URL', 'http://localhost/advance_classroom');`
   - Update if deploying to a different URL

7. **Set upload permissions**
   - Ensure `uploads/` directory is writable by Apache
   - On Windows/XAMPP this is typically already the case

8. **Access the application**
   - Open browser: `http://localhost/advance_classroom`
   - Use demo accounts to test immediately

### AI Chatbot Configuration (Optional)
- Open `api/ai_chat.php`
- Set your OpenRouter API key on line 89: `$apiKey = "sk-or-v1-..."`
- Set preferred model on line 93: `"model" => "nvidia/nemotron-3-super-120b-a12b:free"`
- Get API key from: https://openrouter.ai/keys

---

## 4.2 Teacher Usage Guide

### Getting Started
1. **Login** with teacher credentials (or register as teacher)
2. **Create a class**: Click "Create Class" on dashboard, fill in details
3. **Share the class code**: Students use this 7-character code to join

### Daily Operations

**Post an Announcement:**
- Navigate to your class → Stream tab
- Type your message in the text box
- Optionally attach a file
- Check "Send to All My Classrooms" to broadcast to all your classes
- Click "Post"

**Create an Assignment:**
- Navigate to Classwork tab → Click "Create Assignment"
- Fill in title, instructions, due date, points
- Optionally attach a file
- Toggle "Allow offline submission" if needed
- Click "Post Assignment"

**Upload Materials:**
- Navigate to Materials tab → Click "Add Material"
- Choose type: File Upload, External Link, or Video Link
- Fill in title and description
- Check "Send to All My Classrooms" to share across all classes
- Click "Add Material"

**Import Materials from Another Class:**
- Materials tab → Click "Import from Past Course"
- Browse your other classes, select materials
- Click "Send to Current Classroom"

**Take QR Attendance:**
- Navigate to Attendance tab
- Select duration from dropdown (1–30 minutes)
- Click "Generate QR Code"
- Display the QR code to students (projector/screen)
- Watch live check-in list update in real-time
- Click "Stop" to end the session early

**Grade Submissions:**
- Navigate to Classwork → Click on an assignment
- Click "View All Submissions"
- Enter grade and feedback for each student
- Submit — student is automatically notified

**Create a Quiz:**
- Navigate to Quiz tab → Click "Create Quiz"
- Add title, description, time limit
- Add MCQ questions with options and correct answers
- Save as Draft or publish Live

**Schedule a Video Meet:**
- Navigate to Video Meet tab
- Click "Schedule Meet"
- Enter title and external meet link (Google Meet, Zoom, etc.)
- Set date/time or start immediately

**Archive a Class:**
- Click "Archive / Delete Classroom" button on class page
- Class enters 48-hour grace period (read-only)
- Restore within 48 hours if needed
- After 48 hours, class and all data are permanently deleted

---

## 4.3 Student Usage Guide

### Getting Started
1. **Login** with student credentials (or register as student)
2. **Join a class**: Click "Join Class" on dashboard, enter the class code from your teacher

### Daily Operations

**View Announcements:**
- Navigate to your class → Stream tab
- Read posts from your teacher
- Download attachments if any

**Submit an Assignment:**
- Navigate to Classwork tab → Click on the assignment
- Upload your file or type your response
- Click "Submit" before the due date
- Late submissions are automatically flagged

**View Materials:**
- Navigate to Materials tab
- Preview images, videos, and PDFs directly in browser
- Download files for offline access

**Check In for Attendance:**
- Navigate to Attendance tab
- If teacher has an active QR session, you'll see the QR code and token
- Option 1: Scan the QR code with your phone camera
- Option 2: Enter the displayed token manually
- Option 3: Click "Scan QR with Camera" to use your device camera
- View your attendance stats on the same page

**Take a Quiz:**
- Navigate to Quiz tab when a quiz is live
- Answer MCQ questions within the time limit
- Submit — see your score immediately

**Use Offline Submission:**
- Go to Offline Submission page (from sidebar)
- Select an assignment that allows offline submission
- Generate a QR token
- Show the token/QR to your teacher for verification

**Save Materials from Archived Class:**
- If a class is archived, click "Save All Materials to My Archive"
- Materials are copied to your personal archive
- Access saved materials anytime from the Archive page

**View Your Grades:**
- Navigate to Grades tab in any class
- See all your assignment grades, feedback, and status
- Check your overall performance in Analytics

---

## 4.4 Guardian Usage Guide

1. **Register** as a guardian
2. **Link to a student**: Click "Link Student" → Enter student's email
3. **Wait for approval**: Student must approve the link request
4. **Monitor progress**: View linked student's grades, attendance, and class list
5. **Download materials**: Access materials from student's classes
6. **View analytics**: Click through to detailed performance charts

---

# 5. Conclusion

## 5.1 Summary

SmartClass LMS (Advanced V3.8) is a feature-rich, self-hosted learning management system that covers the complete lifecycle of digital classroom management. With 22 database tables, 10 API endpoints, and role-based dashboards for three user types, it provides:

- **Comprehensive classroom management** — from creation to archival
- **Real-time attendance** via QR code with student-facing display
- **Assessment tools** — assignments, quizzes, polls with auto-grading
- **Content distribution** — materials with preview, import, and bulk broadcast
- **Communication** — announcements, direct messaging, notifications
- **Analytics** — per-student performance dashboards with charts
- **Offline support** — QR token-based submission for disconnected students
- **Guardian oversight** — linked accounts with read-only student progress access
- **AI assistance** — context-aware chatbot for academic queries
- **Calendar integration** — unified view of events and deadlines

## 5.2 Key Technical Achievements

- **Zero frontend framework dependency**: Achieved modern, responsive UI with pure CSS and vanilla JS
- **Progressive enhancement**: Core functionality works without JavaScript; JS adds real-time features (polling, modals, charts)
- **Soft-delete architecture**: 48-hour grace period prevents accidental data loss
- **File deduplication in bulk broadcast**: "Send to All" shares the same file reference across classes without duplicate uploads
- **Real-time QR attendance**: Student auto-detection of active sessions via 3-second polling
- **Security-first**: Prepared statements throughout, bcrypt passwords, XSS escaping, role-based access control

## 5.3 Known Limitations

- **Session sharing**: PHP sessions are cookie-based; logging into a different account in another tab on the same browser overwrites the current session. Use different browsers or incognito mode for multi-account testing.
- **No real-time push**: Uses polling (3s interval) instead of WebSockets for live features
- **External QR API**: QR code generation depends on `api.qrserver.com` availability
- **AI chatbot**: Dependent on OpenRouter API availability and free-tier rate limits
- **Video meet**: Uses external links rather than built-in video conferencing
- **No mobile app**: Responsive web design only; no native mobile application

## 5.4 Future Enhancements

- WebSocket integration for true real-time features (live chat, instant notifications)
- Built-in video conferencing via WebRTC
- Mobile application (React Native or Flutter)
- AI-powered auto-grading for text submissions
- Plagiarism detection integration
- Parent-teacher communication portal
- Attendance geofencing verification
- Course curriculum mapping and learning paths
- Multi-language support (i18n)
- Dark/light theme toggle
- Two-factor authentication (2FA)

---

*Document generated for SmartClass LMS Advanced V3.8*
*Database: 22 tables | API Endpoints: 10 | User Roles: 3 (Teacher, Student, Guardian)*
