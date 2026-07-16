<?php

namespace App\Enums;

/**
 * The operator's per-row decision for a staged `duplicate` row (spec 0036,
 * `import_run_rows.resolution`): `skip` writes nothing, `create` forces a
 * brand-new Referent+Lead ignoring the match, `update` targets the matched
 * Referent's own Lead in the run's campaign. A still-null column (never
 * resolved) is treated exactly like `skip` at commit time.
 */
enum ImportRowResolution: string
{
    case Skip = 'skip';
    case Create = 'create';
    case Update = 'update';
}
