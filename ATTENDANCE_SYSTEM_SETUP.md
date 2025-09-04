# BINHS Barcode Attendance System - Complete Setup Guide

## System Overview
The BINHS Attendance System uses barcode scanning with daily reset functionality to track student attendance. Each student can scan only once per day, with automatic system reset at midnight.

## Components Installed

### 1. Database Schema
- **students table**: Added `barcode` and `is_scanned` columns
- **daily_attendance table**: Stores daily attendance records
- **daily_reset_log table**: Tracks automatic reset operations

### 2. PHP Files Created/Modified

#### Core System Files:
- `attendance_scanner.php` - Main scanning interface with daily reset status
- `view_grades.php` - Student list with "View Barcode" modal functionality
- `student_grades.php` - Student registration with barcode generation

#### Database Setup Files:
- `add_daily_reset.php` - Adds is_scanned column and daily_reset_log table
- `generate_barcodes_for_existing.php` - Generates barcodes for existing students

#### Daily Reset System:
- `midnight_reset.php` - Automatic daily reset script (runs at midnight)
- `manual_reset.php` - Manual reset tool for testing and maintenance

## Setup Instructions

### Step 1: Database Setup
1. Run the daily reset database setup:
```bash
php add_daily_reset.php
```

2. Generate barcodes for existing students:
```bash
php generate_barcodes_for_existing.php
```

### Step 2: Test the System

#### Test Barcode Generation:
1. Go to `student_grades.php`
2. Add a new student or edit existing student
3. Click "Generate Barcode" button
4. Verify barcode is generated (format: 25 + padded student number)

#### Test Barcode Display:
1. Go to `view_grades.php`
2. Click "View Barcode" button for any student
3. Verify modal opens with barcode display
4. Check that barcode renders correctly using JsBarcode library

#### Test Attendance Scanner:
1. Go to `attendance_scanner.php`
2. Click in the barcode input field
3. Scan a student barcode or type manually (e.g., "2500001")
4. Verify attendance is recorded and student marked as scanned
5. Try scanning same barcode again - should show "already scanned" message

### Step 3: Daily Reset Testing
1. Test manual reset:
```bash
php manual_reset.php
```

2. Verify reset worked:
   - Check `attendance_scanner.php` - all students should show "can still scan"
   - Verify `daily_reset_log` table has reset entry

### Step 4: Set Up Automatic Daily Reset
Set up a cron job to run `midnight_reset.php` daily at midnight:

#### Windows (Task Scheduler):
1. Open Task Scheduler
2. Create Basic Task
3. Set trigger: Daily at 12:00 AM
4. Set action: Start program
5. Program: `php.exe`
6. Arguments: `"C:\path\to\your\project\midnight_reset.php"`

#### Linux/macOS (Crontab):
```bash
# Add to crontab
0 0 * * * php /path/to/your/project/midnight_reset.php
```

## Hardware Setup - TEKLEAD Scanner

### Scanner Configuration:
1. Connect TEKLEAD1D&2D scanner via USB
2. Scanner operates in keyboard emulation mode
3. No additional drivers needed
4. Scanner will automatically input scanned data as keyboard input

### Barcode Compatibility:
- System generates CODE128 barcodes
- Format: Numeric only (e.g., "2500001", "2500002")
- Compatible with TEKLEAD scanner specifications
- Uses JsBarcode library for rendering

## Daily Operations

### Morning Setup:
1. Open `attendance_scanner.php`
2. Verify daily reset status shows correct counts
3. Ensure scanner is connected and working
4. Click in barcode input field to activate scanner

### Throughout the Day:
- Students scan their barcodes once per day
- System automatically determines on-time vs late status
- View real-time statistics on scanner page
- Monitor "Can Still Scan" count

### Evening Review:
- Check daily attendance log
- Review attendance statistics
- System will automatically reset at midnight

## Troubleshooting

### Barcode Not Scanning:
1. Verify scanner is connected and powered
2. Check if barcode is clear and not damaged
3. Ensure correct barcode format (numeric only)
4. Try manual entry of barcode number

### Daily Reset Not Working:
1. Check if cron job/task scheduler is running
2. Verify `midnight_reset.php` has correct permissions
3. Check `daily_reset_log` table for error messages
4. Run manual reset as fallback

### Student Shows "Already Scanned":
1. This is normal - each student can scan only once per day
2. Check if it's a duplicate entry
3. Wait for midnight reset or use manual reset for testing

### Modal Not Opening:
1. Verify Bootstrap 5 is loaded
2. Check browser console for JavaScript errors
3. Ensure JsBarcode library is properly loaded

## File Locations

### Main System Files:
- Scanner Interface: `attendance_scanner.php`
- Student Management: `student_grades.php`
- Grade Viewer: `view_grades.php`

### Maintenance Files:
- Daily Reset: `midnight_reset.php`
- Manual Reset: `manual_reset.php`
- Setup Scripts: `add_daily_reset.php`, `generate_barcodes_for_existing.php`

### Database Tables:
- `students` - Student information with barcodes and scan status
- `daily_attendance` - Daily attendance records
- `daily_reset_log` - Reset operation logs

## Security Notes

1. Scanner input is automatically sanitized and validated
2. Barcode format is strictly enforced (numeric only)
3. SQL injection protection via prepared statements
4. Daily reset logs maintain audit trail
5. Each student limited to one scan per day

## Support and Maintenance

### Regular Maintenance:
- Monitor daily reset logs weekly
- Check attendance data accuracy
- Verify scanner hardware functionality
- Backup database regularly

### Emergency Procedures:
- Use `manual_reset.php` if automatic reset fails
- Check scanner hardware connections
- Verify database connectivity
- Contact system administrator for database issues

---

**System Status**: Fully operational with daily reset functionality
**Last Updated**: <?php echo date('F j, Y g:i A T'); ?>
**Scanner Model**: TEKLEAD1D&2D USB Barcode Scanner
**Timezone**: Asia/Manila (Philippines)
