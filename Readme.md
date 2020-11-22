# TYPO3 Extension: Redirects Health Check

It provides a Health check command for redirects.

## Usage

You can execute it on cli or via scheduler. The result is written to the redirect records

```sh
Description:
  Check health of redirects

Usage:
  redirects:checkhealth [options]

Options:
  -s, --siteIdentifier[=SITEIDENTIFIER]  Site is used for wildcard source hosts. It defaults to first site found.
  -d, --disable                          Disable unhealthy redirects
  -h, --help                             Display this help message
  -q, --quiet                            Do not output any message
  -V, --version                          Display this application version
      --ansi                             Force ANSI output
      --no-ansi                          Disable ANSI output
  -n, --no-interaction                   Do not ask any interactive question
  -v|vv|vvv, --verbose                   Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

Make sure to use the correct `TYPO3_CONTEXT` in order to use a different site variant. 