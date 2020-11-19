# TYPO3 Extension: Redirects Healthcheck

It provides a healthcheck command for redirects. Redirects with unreachable destinations will be deactivated.

It also comes with the some fields in redirect records:

* Inactive reason (Reason why it was deactived)
* Last healthcheck (Date when it was checked)

## Usage

You can execute it on cli or via scheduler.

```sh
./typo3/sysext/core/bin/typo3 redirects:checkhealth
```
