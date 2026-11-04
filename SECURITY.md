# Maintenance and security policy

## Supported versions

The project aims to maintain the current major version and its immediate predecessor. For the
transition to v5, v3 receives a final, limited security-support period.

All deadlines below are measured from the first **stable v5.0 release**. Prereleases do not start
these periods. Calendar end-of-life dates will be published when v5.0 stable is released.

| Version | Scope | End of support |
| --- | --- | --- |
| v5 | Active development, bug fixes, security fixes, and compatibility with new PHP versions | Active support while it is the current major version; a successor's transition policy will be announced separately |
| v4 | Security fixes and important backward-compatible bug fixes; no new features | 12 months after v5.0 stable |
| v3 | Critical security fixes only, where a backport is feasible | 3 months after v5.0 stable |

New PHP compatibility updates for v3 stop with the announcement of this policy. Existing releases
may continue to work with newer PHP versions, but no further compatibility updates are promised.
Support for a library version does not extend the upstream support lifetime of its PHP runtime;
see the [PHP supported versions](https://www.php.net/supported-versions.php).

## Backport policy

- Fixes are developed and tested on v5 first, then considered individually for supported older branches.
- New Unicode data and changes to IDNA processing rules are introduced in v5. They are not routinely
  backported because they can change conversion results and the handling of stored domain names.
- Security-relevant validation defects are assessed individually, including whether a fix must change
  previously accepted input or output. Any resulting compatibility impact is documented in the release notes.
- v4 receives important bug fixes only when they can preserve backward compatibility. Its longer
  transition period gives applications time to adopt v5's stricter validation, normalization, and exceptions.
- v3 receives only critical security fixes where a backport is feasible; ordinary bug fixes, new features,
  Unicode updates, and support for new PHP versions are outside its maintenance scope.

## End of life and exceptional maintenance

After a branch reaches end of life, no further fixes or compatibility updates are guaranteed. Existing
releases and branches remain available; end of life does not disable installed copies of the library.
Users should migrate to a supported version using the [upgrading notes](./UPGRADING.md).

Any extended v3 maintenance requires a separate, explicit agreement based on a concrete downstream
need or funded maintenance. Such an exception does not extend the general support period for v3.
