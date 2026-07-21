<?php

namespace App\Enums;

/**
 * Bulk operator-assignment strategy (spec 0048, business-rule br-balanced):
 * `single` assigns every target to the same chosen operator; `balanced`
 * distributes them across the Sede's operators via LeadOperatorDistributor.
 * Shared by the real-lead bulk-assign endpoint (AssignOperatorsRequest) and
 * the import bulk-assign endpoint (BulkAssignRequest).
 */
enum LeadAssignmentMode: string
{
    case Single = 'single';
    case Balanced = 'balanced';
}
