# PSGC reference data

Source: `@jobuntux/psgc` version 0.2.1, bundled `data/2025-2Q` JSON files.
Upstream: https://github.com/jobuntux/psgc

The copied dataset is pinned to 2025 Q2, not a live/latest geographic feed.
Its upstream MIT license is included in this directory.
`PsgcGeographySeeder` reads these files without requiring Node at runtime.
It adds labelled province-equivalent groups for unmatched city parents to
support the four-level address selector; those groups are application conveniences.
