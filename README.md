# kahoochkinalfa
DAW n2 first project
# Quiz Application

## Table of Contents
- [Introduction](#introduction)
- [Features](#features)
- [Installation](#installation)
  - [Requirements](#requirements)
  - [Setup](#setup)
- [Usage](#usage)
  - [Player Mode](#player-mode)
  - [Admin Mode](#admin-mode)
- [Database Structure](#database-structure)
- [Sustainable Development Goal (SDG) Alignment](#sustainable-development-goal-sdg-alignment)
- [Future Improvements](#future-improvements)
- [License](#license)

---

## Introduction
This project is a **web-based quiz platform** that allows:
- Users to play quizzes interactively with a timer.
- Administrators to create, edit, and manage quizzes, questions, and answers via a secure interface.

The application was built as a lightweight educational tool with a focus on accessibility, ease of use, and extendability.

---

## Features
- **User login/logout system** with role-based access.
- **Quiz catalog**: players can select which quiz to play.
- **Timer per question** to simulate exam-like pressure.
- **Automatic scoring** after completion.
- **Admin interface** for:
  - Creating new quizzes.
  - Adding questions with optional images.
  - Editing and deleting questions.
  - Managing answers and correct responses.
- **Database-driven** so quizzes and results are persistent.

---

## Installation

### Requirements
- PHP 8.x+
- MySQL 5.7+ or MariaDB
- Apache/Nginx (with PHP support)
- Modern browser (Chrome, Firefox, Edge)

## Usage

### Player Mode
- Players can select a quiz from the dropdown list.
- Optionally enable **shuffle** to randomize questions and answers.
- Each question is timed (30 seconds by default).
- At the end, players receive their **score**.

### Admin Mode
- Admins must log in with valid credentials.
- After login, the **Admin Area** appears.
- Admins can:
- Create new quizzes.
- Edit quiz metadata (title, UID).
- Add new questions (with optional image URLs).
- Update or delete existing questions.

---

## Database Structure
- **quizzes**: stores quiz metadata (`id`, `quiz_uid`, `title`, `owner_user_id`).
- **questions**: stores each quiz’s questions (`id`, `quiz_id`, `pregunta`, `resposta_correcta`, `imatge`).
- **answers**: stores the 4 possible answers for each question (`id`, `question_id`, `ordre`, `etiqueta`).
- **users**: stores user credentials and roles (admin/player).

---

## Sustainable Development Goal (SDG) Alignment
This project directly supports **SDG 4: Quality Education**.

- **Target 4.4**: Increasing the number of youth and adults who have relevant skills, including ICT skills.
- The application provides a free, customizable quiz tool that can be used in schools, universities, and training programs.
- By allowing teachers and trainers to **create tailored quizzes**, it promotes **accessible, interactive, and digital learning** experiences.
- Students benefit from **self-assessment** and **instant feedback**, fostering better engagement and learning outcomes.

---

## Future Improvements
- Add multi-language support (i18n).
- Add user statistics and performance tracking.
- Enable image uploads (instead of URLs).
- Implement categories/tags for quizzes.
- Mobile-first design improvements.
