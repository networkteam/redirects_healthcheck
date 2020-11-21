# TYPO3 Extension: Redirects Healthcheck

It provides a healthcheck command for redirects. Redirects with unreachable destinations will be deactivated.

It also comes with the some fields in redirect records:

* Inactive reason (Reason why it was deactivated)
* Last healthcheck (Date when it was checked)

## Usage

You can execute it on cli or via scheduler.

```sh
./typo3/sysext/core/bin/typo3 -h redirects:checkhealth

Description:
  Check health of redirects and disable them in case of unreachable destinations

Usage:
  redirects:checkhealth [<siteIdentifier>]

Arguments:
  siteIdentifier        Site is used for wildcard source hosts. It defaults to first site found.

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

Make sure to use the correct `TYPO3_CONTEXT` in order to use a different site variant. 