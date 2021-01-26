# Plugin enrol_shared

## Description

This enrol plugin is based on enrol_manual. This is a very simple enrolment method made to work with mod_sharedurl.

The principle is that it works without any teacher manipulation and it never notify user when he is enrolled or unenrolled.

## Purpose

- Work with mod_sharedurl to automatically enrol users in a course when they click on a 'sharedurl' activity. The aim is to be able to give access to an activity for a student who is not enrolled in the course.

- The enrolment process should be as inconspicuous as possible. It only serves to bypass the standard moodle security that requires students to be enrolled in the course. (So we disable notifications)
