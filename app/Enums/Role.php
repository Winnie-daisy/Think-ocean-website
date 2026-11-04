<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasDescription, HasLabel
{
    case Administrator = 'admin';
    case Editor = 'editor';
    case Contributor = 'contributor';
    case Reviewer = 'reviewer';

    /**
     * Permission map. Policies consult this; it is the single source of truth
     * for what each role may do.
     *
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Administrator => Permission::cases(),
            self::Editor => [
                Permission::ViewContent,
                Permission::CreateContent,
                Permission::EditAnyContent,
                Permission::PublishContent,
                Permission::DeleteContent,
                Permission::UploadMedia,
                Permission::ManageMedia,
                Permission::ManageTaxonomy,
                Permission::ManageNavigation,
                Permission::ViewSubmissions,
                Permission::ManageSubmissions,
            ],
            self::Contributor => [
                Permission::ViewContent,
                Permission::CreateContent,
                Permission::UploadMedia,
            ],
            self::Reviewer => [
                Permission::ViewContent,
            ],
        };
    }

    public function allows(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Editor => 'Editor / Approver',
            self::Contributor => 'Content Contributor',
            self::Reviewer => 'Read-only Reviewer',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Administrator => 'Full access, including users, settings and the activity log.',
            self::Editor => 'Creates and edits all content, reviews submissions and publishes.',
            self::Contributor => 'Creates and edits their own drafts and submits them for review. Cannot publish.',
            self::Reviewer => 'Can view content and review history. Cannot change anything.',
        };
    }
}
