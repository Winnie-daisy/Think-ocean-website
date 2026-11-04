<?php

namespace App\Enums;

enum Permission: string
{
    case ViewContent = 'content.view';
    case CreateContent = 'content.create';
    case EditAnyContent = 'content.edit-any';
    case PublishContent = 'content.publish';
    case DeleteContent = 'content.delete';
    case UploadMedia = 'media.upload';
    case ManageMedia = 'media.manage';
    case ManageTaxonomy = 'taxonomy.manage';
    case ManageNavigation = 'navigation.manage';
    case ManageSettings = 'settings.manage';
    case ManageUsers = 'users.manage';
    case ViewSubmissions = 'submissions.view';
    case ManageSubmissions = 'submissions.manage';
    case ViewActivityLog = 'activity.view';
}
