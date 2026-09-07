# Canopy editorial assurance

NIDirect owns the DDEV entry point and estate inventory for NIDirect, DEPT, and
all exported Unity sites. The command boots
Canopy through NIDirect's Composer autoloader, so normal use executes the
version locked under `vendor/dof-dss/canopy`.

## Pending Canopy release

The current lockfile installs Canopy 1.0.0. The expanded NICS baseline,
`--detail` and `--status` reporting, and expected-site inventory checks require
the next Canopy release containing commits `bcc6f02` and `24cf0f3`. Until that
release is selected in `composer.lock`, use the local development overlay below
when exercising this estate comparison. This integration is being committed in
advance so its consumer-owned inventory and DDEV wiring can be reviewed without
pretending that the released package already provides the new behaviour.

Audit NIDirect against the vendor-provided NICS baseline:

```shell
ddev canopy audit editorial
ddev canopy audit editorial --detail
ddev canopy audit editorial --status=fail,unknown
ddev canopy audit editorial --status=unknown
```

## Estate comparison

The estate command is a DDEV host command. It still boots the package installed
in NIDirect's `vendor` directory, but runs on the host so it can read sibling
DEPT and Unity checkouts without adding container mounts. It expects this local
layout:

```shell
dof-dss/
├── nidirect-drupal/
├── dept/
└── unity/
```

Run the comparison directly from NIDirect:

```shell
ddev canopy-editorial --detail
ddev canopy-editorial --status=fail,unknown
ddev canopy-editorial --status=unknown
ddev canopy-editorial --format=json --status=fail,unknown
```

Canopy discovers all exported Unity sites below
`unity/project/config/*/config`, then compares them with the explicit tenant IDs
in the inventory. A missing expected export is reported as `unknown`; a newly
discovered but undeclared site is reported as `warn`.

Filtering changes displayed results only. The command exit code still reflects
the complete audit, so a hidden failure or unknown result remains non-zero.

## Initial estate review

The first complete exported-config review discovered all 18 expected sites: one
NIDirect site, one DEPT site, and 16 Unity sites. All Unity tenants produced the
same capability statuses and the same semantic Basic HTML toolbar, enabled
filter set, and moderation workflow. Their current failures are therefore
shared Unity platform differences rather than tenant-specific drift:

- no Unity tenant exports Simple XML Sitemap configuration; Uregni alone enables
  the separate HTML `sitemap` module, which is not treated as an XML sitemap
  substitute;
- all Unity Basic HTML toolbars omit `importWord` and `textPartLanguage`; and
- all Unity Basic HTML formats configure `token_filter` but leave it disabled.

The absent optional `schema_metatag` extension remains `skipped`. These findings
have not been registered as exceptions merely because they are common across
Unity. The command output is the current authority if configuration changes
after this initial review.
