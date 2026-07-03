<?php

namespace App\Enums;

use App\Enums\Attributes\IsDefault;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Contractual qualification/cost category of a user's employment profile
 * (spec 0015). Single source of truth for the allowed values, shared by the
 * model cast, the nested `employment.qualification_type` validation and the
 * grid column's set-filter options/enumKey.
 */
enum QualificationTypeEnum: string
{
    use HasMeta;

    #[Label('Employee level 5')]
    #[IsDefault(true)]
    case EmployeeLevel5 = 'employee_level_5';

    #[Label('Administrative')]
    case Administrative = 'administrative';

    #[Label('Coordinator')]
    case Coordinator = 'coordinator';

    #[Label('ISO consultant')]
    case IsoConsultant = 'iso_consultant';

    #[Label('Teacher (co.co.co)')]
    case TeacherCococo = 'teacher_cococo';

    #[Label('Teacher (VAT)')]
    case TeacherVat = 'teacher_vat';

    #[Label('Trainee cost')]
    case TraineeCost = 'trainee_cost';

    #[Label('Hourly cost (M/E)')]
    case HourlyCostMe = 'hourly_cost_me';

    /**
     * The supported values (the `value` of every case), for validation rules
     * and option lists.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
