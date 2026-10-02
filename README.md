# HRIS Management System

## Project Information

**Project Title:** HRIS Management System  
**Team Name:** Code Alchemists  
**Client:** MY Outsourcing Ltd. (MYOL)  
**University:** East West University  
**Course:** CSE412 - Software Engineering 

---

## Overview

The **HRIS Management System** is a web-based Human Resource Information System developed for **MY Outsourcing Ltd. (MYOL)**.

The system is intended to centralize and partially automate employee management, RFID-based attendance tracking, work schedule management, salary processing, leave management, reporting, and administrative activities.

The system will use RFID card scanning to record employee entry and exit times. The recorded attendance information can then be used for attendance monitoring, late/absence evaluation, and salary processing according to the defined rules of the organization.

The system will also provide role-based access so that administrators and employees can access the functionalities relevant to their roles.

---

## Main Features

The initial requirements of the system include:

1. **Admin Account & Profile**
   - Administrator authentication
   - Admin account creation and management
   - Admin profile management

2. **Employee Account & Profile**
   - Employee authentication
   - Employee profile management
   - Employee information access

3. **RFID Employee Identification**
   - RFID card registration
   - Assign RFID cards to employees
   - Validate unique RFID assignments

4. **RFID Entry Attendance**
   - Record employee entry using RFID
   - Automatically record entry date and time

5. **RFID Exit Attendance**
   - Record employee exit using RFID
   - Associate exit time with the corresponding attendance record

6. **Attendance History**
   - View attendance records
   - View entry and exit times

7. **Administrative Attendance Management**
   - View employee attendance
   - Filter attendance records
   - Identify late and absent employees

8. **Work Schedule Management**
   - Define work schedules
   - Set expected working/entry times
   - Assign schedules to employees

9. **Salary Processing**
   - Maintain employee salary information
   - Define salary rules
   - Use attendance information for salary processing

10. **Leave & Holiday Management**
    - Employee leave applications
    - Leave approval/rejection
    - Organizational holiday management

11. **Reports & Excel Export**
    - Generate attendance reports
    - Generate leave reports
    - Generate salary reports
    - Export relevant records to Excel

12. **Audit Logs**
    - Record important system activities
    - Associate activities with users
    - Review audit records

---

## User Roles

### Administrator

Administrators will have access to administrative functionalities such as:

- Managing administrator and employee accounts
- Managing RFID assignments
- Managing attendance
- Managing work schedules
- Processing salaries
- Managing leave and holidays
- Generating reports
- Reviewing audit logs

### Employee

Employees will have access to functionalities such as:

- Logging into the system
- Viewing their profile
- Viewing their attendance history
- Applying for leave

---

## RFID Attendance Workflow

The basic attendance workflow is:

```text
Employee
    ↓
RFID Card
    ↓
RFID Reader
    ↓
HRIS Management System
    ↓
Identify Employee
    ↓
Record Entry / Exit Time
    ↓
Database
```

---

## Overall Architecture

The HRIS Management System will follow the Model-View-Controller (MVC) architectural pattern.

MVC separates the system into three main components:

### Model

The Model manages:

System data
Database operations
Business logic
Data processing

### View

The View manages:

User interface
Presentation of information
Employee and administrator interfaces
Display of system results

### Controller

The Controller manages:

User requests
User interactions
Communication between the View and Model
Application flow
Architecture Flow

```text
                    HRIS Management System
                            │
        ┌───────────────────┼───────────────────┐
        │                   │                   │
      View             Controller             Model
        │                   │                   │
        │            User Requests              │
        │                   │                   │
        └───────────────────┴───────────────────┘
                            │
                            ▼
                         Database
```

---

## RFID Integration

The RFID reader will provide employee identification information to the system.

```text
Employee
    │
    ▼
RFID Card
    │
    ▼
RFID Reader
    │
    ▼
Controller
    │
    ▼
Model
    │
    ▼
Database
```
The MVC architecture provides separation of concerns and allows the system to be developed and maintained in an organized manner.

---

## Proposed Technology

The project is planned as a web-based application using the following technologies and components:

```text
| Technology / Component | Purpose                             |
| ---------------------- | ----------------------------------- |
| **HTML**               | Structure of web pages              |
| **CSS**                | User interface styling and layout   |
| **PHP**                | Server-side application development |
| **MySQL**              | Database management                 |
| **Laragon**            | Local development environment       |
| **RFID Reader**        | Reading employee RFID cards         |
| **RFID Cards**         | Employee identification             |
```

---

## Hardware

The project will use:
```text
JT308 RFID Card Reader USB – 125kHz
EM4100 125kHz RFID Cards
```
The RFID reader will be connected to a Windows PC and used to read employee RFID cards for attendance identification.

---

## Project Development

The project will be developed incrementally through multiple development sprints.

Sprint 0 – Initial Requirement and Design

The initial Sprint 0 focuses on establishing the foundation of the system.

## The main activities include
```text
• Requirement identification
• User Story preparation
• Acceptance Criteria definition
• Requirement prioritization
• Use Case Diagram development
• Overall system architecture design
• Project documentation
• Initial project repository setup
```
The project requirements have been organized into 12 User Stories covering the major functionalities of the HRIS Management System.

## The corresponding Use Case Diagrams are organized as:
```text
Level 1
    │
    └── Overall HRIS Management System
            │
            ├── Level 1.1  Admin Account & Profile
            ├── Level 1.2  Employee Account & Profile
            ├── Level 1.3  RFID Employee Identification
            ├── Level 1.4  RFID Entry Attendance
            ├── Level 1.5  RFID Exit Attendance
            ├── Level 1.6  Attendance History
            ├── Level 1.7  Administrative Attendance Management
            ├── Level 1.8  Work Schedule Management
            ├── Level 1.9  Salary Processing
            ├── Level 1.10 Leave & Holiday Management
            ├── Level 1.11 Reports & Excel Export
            └── Level 1.12 Audit Logs
```

---

## Team
Code Alchemists

### Team Lead

Md. Saad Al Hasan

### Team Members

Md. Mahamudul Hasan

Radwan Rahman Ratul

---

## Client

MY Outsourcing Ltd. (MYOL)

The system is being developed as part of the CSE412 project at East West University.

## License

This project is developed for academic purposes as part of the CSE412 - Software Engineering course at East West University.

