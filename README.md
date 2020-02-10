# Normal Usage

## Install the module
1. Download or require the module to your project
2. Enable the module
3. If you want to setup the cloud environment usage for testing or content archive reasons, visit /admin/config/content/osk_content_export_settings or set that up in settings.php.

## Export on any environment as zip
1. Go to any content and click the export tab
2. Add a filename without the extension
3. Check away dependencies if you don't want any
4. Export the user if you want to add that specific user.
5. If you want to obfuscate some field fill that in.
6. Choose "All in one zip package"
7. Click export and a zip file will be created

## Import on any environment from a zip
1. Go to /admin/content/osk-import
2. Upload a zip file
3. If you want to import a file on the server, use Server Path instead
4. Fill in extra options if you want
5. Import

## Export to the cloud
1. Follow steps 1-5 of "Export on any environment as zip"
2. Choose "Upload to cloud"
3. A yaml file will be created

## Import from the cloud
1. Go to /admin/content/osk-import
2. Upload a yaml file
3. If you want to import a file on the server, use Server Path instead
4. Fill in extra options if you want
5. Import

# Programatical usage
Use the function `osk_content_import_import($file, $uri = '', $uid = NULL, $remove_timestamp = TRUE)`

# Usage in Behat
Use the following command `Given the content :contentfile exists on :uri`
