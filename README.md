# chrisg/shell

This package was extracted from the monorepo (shell) preserving history.

Requires:
- chrisg/fw (^1.0)
- chrisg/htmlload-skeleton (^1.0)

# Shell - Command Line Utilities

Shell is a collection of command-line utilities for text processing, file manipulation, and web page screenshot capture.

## Features

### Text Processing
- Remove duplicate lines from text
- Sort lines alphabetically
- Group lines by tokens

### File Manipulation
- Replace text in files
- Process piped input

### Web Utilities
- Capture screenshots of web pages using Chrome

## CLI Commands

### Text Processing

#### undupe
Removes duplicate lines from input.

```
php index.php /shell undupe -file="filename.txt"
```
or
```
cat filename.txt | php index.php /shell undupe -file
```

#### sort
Sorts lines alphabetically.

```
php index.php /shell sort -file="filename.txt"
```
or
```
cat filename.txt | php index.php /shell sort -file
```

#### group
Groups lines by tokens.

```
php index.php /shell group -file="filename.txt"
```
or
```
cat filename.txt | php index.php /shell group -file
```

### File Manipulation

#### replace
Replaces text in a file or piped data.

```
php index.php /shell replace -search="searchstring" -replace="replacestring" -file="filename.txt"
```
or
```
dir | php index.php /shell replace "searchstring" "replacestring" -file
```

When using a file, the output is saved to `filename.txt.new`.

### Web Utilities

#### screenshot
Takes a screenshot of a web page using Chrome.

```
php index.php /shell screenshot -url="/"
```

Options:
- `-small` - Capture a smaller screenshot (800x600 instead of 1920x1200)

The screenshot is saved to `out.jpg` in the project root directory.

## Setup and Configuration

1. Ensure PHP is installed and configured
2. For the screenshot functionality, Google Chrome must be installed in the default location

## Development

The Shell component uses the `CliHandler` trait to process command-line arguments and provide a consistent interface for all commands.

## License

This project is proprietary software.
