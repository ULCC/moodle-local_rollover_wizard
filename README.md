# Content Rollover Wizard

A comprehensive Moodle plugin for managing course content rollover operations with enhanced file URL processing and management capabilities.

## Overview

The Rollover Wizard plugin provides tools for managing course content transfers between academic periods, with specialized handling for file URLs and content migration. It includes both automated scheduled tasks and manual CLI tools for processing course sections and resolving file accessibility issues.

## Features

- **Course Content Rollover**: Transfer content between courses with proper context handling
- **File URL Processing**: Convert hardcoded `pluginfile.php` URLs to `@@PLUGINFILE@@` format
- **File Management**: Automatic file copying between contexts and fileareas
- **Change Tracking**: Comprehensive logging for all modifications with rollback capability
- **CLI Tools**: Command-line utilities for maintenance and troubleshooting
- **Scheduled Tasks**: Automated processing of URL conversions and file management

## Installation

### Installing via uploaded ZIP file

1. Log in to your Moodle site as an admin and go to _Site administration > Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

### Installing manually

The plugin can be also installed by putting the contents of this directory to:

    {your/moodle/dirroot}/local/rollover_wizard

Afterwards, log in to your Moodle site as an admin and go to _Site administration > Notifications_ to complete the installation.

Alternatively, you can run:

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## Configuration

After installation, configure the plugin settings at:
_Site administration > Plugins > Local plugins > Rollover Wizard_

Key settings:
- **Replace URL Limit**: Number of sections to process per scheduled task execution
- **Scheduled Task Settings**: Configure automatic URL processing intervals

## CLI Tools

The plugin provides several command-line tools located in the `cli/` directory:

### File URL Management

#### `fix_file_urls.php`
Repairs broken file URLs in course sections after rollover operations.

```bash
# Fix all courses
php local/rollover_wizard/cli/fix_file_urls.php --confirm

# Fix specific course
php local/rollover_wizard/cli/fix_file_urls.php --course=123 --confirm

# Dry run with verbose output
php local/rollover_wizard/cli/fix_file_urls.php --dry-run --verbose
```

Options:
- `--course=ID`: Process specific course only
- `--dry-run`: Preview changes without applying them
- `--verbose`: Show detailed processing information
- `--confirm`: Required for actual changes

#### `check_old_urls.php`
Identifies sections containing hardcoded `pluginfile.php` URLs.

```bash
# Check all courses
php local/rollover_wizard/cli/check_old_urls.php

# Check specific course
php local/rollover_wizard/cli/check_old_urls.php 123
```

### Section Management

#### `rollback_sections.php`
Rollback section changes recorded in the rollover wizard log.

```bash
# Preview all rollbacks
php local/rollover_wizard/cli/rollback_sections.php --dry-run --verbose

# Rollback all sections
php local/rollover_wizard/cli/rollback_sections.php --confirm

# Rollback specific course
php local/rollover_wizard/cli/rollback_sections.php --course=123 --confirm

# Rollback specific section
php local/rollover_wizard/cli/rollback_sections.php --section=456 --confirm

# Force rollback when content has been modified
php local/rollover_wizard/cli/rollback_sections.php --confirm --force
```

Options:
- `--course=ID`: Rollback sections in specific course only
- `--section=ID`: Rollback specific section only
- `--dry-run`: Preview rollbacks without applying them
- `--verbose`: Show detailed processing information
- `--force`: Override validation when content has been modified
- `--confirm`: Required for actual rollback operations



## Scheduled Tasks

### Replace Pluginfile URLs Task

**Class**: `local_rollover_wizard\task\replace_pluginfile_urls`

This task automatically processes course sections containing hardcoded `pluginfile.php` URLs and:

1. **Parses URLs**: Extracts `pluginfile.php/contextid/component/filearea/itemid/filename` from section summaries
2. **Copies Files**: Transfers files from source contexts to target section fileareas
3. **Converts URLs**: Changes hardcoded URLs to `@@PLUGINFILE@@/filename` format
4. **Logs Changes**: Records all modifications in `local_rollover_wizard_sectionlog` table

#### Manual Execution

```bash
# Execute the scheduled task manually
php admin/cli/scheduled_task.php --execute='local_rollover_wizard\task\replace_pluginfile_urls'
```

#### Configuration

- Configure processing limits in plugin settings
- Task respects the "Replace URL Limit" setting for batch processing
- Automatic retry on next scheduled execution if processing is incomplete

#### Monitoring

The task provides detailed logging including:
- Number of sections processed
- Files copied with sizes and filenames
- Processing statistics and execution time
- Error reporting for failed operations

## Database Schema

### Tables

#### `local_rollover_wizard_log`
Main rollover operation log with task details and status tracking.

#### `local_rollover_wizard_sectionlog` 
Section-specific change tracking for URL replacements and modifications.

Fields:
- `sectionid`: Course section ID
- `courseid`: Course ID
- `oldsummary`: Original section summary before changes
- `newsummary`: Modified section summary after changes
- `timecreated`: Timestamp of the change

#### `rollover_wizard_coursesize`
Course size calculation and caching table.

## File Management

### File Copying Process

The plugin implements intelligent file copying between contexts:

1. **Multi-Strategy Search**: Locates files across different contexts and fileareas
2. **Context Mapping**: Ensures files are placed in appropriate target contexts
3. **Duplicate Prevention**: Avoids unnecessary file duplication
4. **Integrity Preservation**: Maintains file metadata and access permissions

### Supported File Operations

- Copy files between course contexts
- Transfer files from module contexts to course sections
- Handle files from draft areas and user contexts
- Process files across different rollover generations

## Troubleshooting

### Common Issues

#### Files Not Accessible After Rollover
Use the `fix_file_urls.php` CLI tool to repair broken file references.

#### URL Conversion Not Working
1. Check scheduled task is enabled and running
2. Verify plugin settings for processing limits
3. Use `check_old_urls.php` to identify problematic URLs

#### Rollback Failures
Use the `--force` option with `rollback_sections.php` if content has been modified since logging.

### Debug Information

Enable developer debugging in Moodle for detailed error information:
- Error messages include stack traces
- CLI tools provide verbose output options
- Scheduled tasks log detailed processing information

## Development

### Testing

The plugin includes comprehensive testing tools:
- URL parsing validation
- File copying verification
- Rollback functionality testing
- Scheduled task execution testing

### Contributing

When contributing to the plugin:
1. Follow Moodle coding standards
2. Include appropriate PHPDoc comments
3. Test CLI tools with various scenarios
4. Verify scheduled task functionality
5. Update documentation for new features

## Support

For support and bug reports, please contact:
- **Developer**: Cosector Development <dev@cosector.co.uk>
- **GitHub**: Create issues in the project repository

## License

2025 Cosector Development <dev@cosector.co.uk>

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.