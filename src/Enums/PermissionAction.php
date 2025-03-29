<?php

namespace SaeidSharafi\LaravelPermissionGenerator\Enums;

enum PermissionAction: string
{
    // Standard CRUD actions
    case VIEW = 'view';
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';

    // Scoped actions - generate both ANY and regular versions
    case VIEW_SCOPED = 'view_scoped';
    case UPDATE_SCOPED = 'update_scoped';
    case DELETE_SCOPED = 'delete_scoped';
    case CONTROL_SCOPED = 'control_scoped';
    case VIEW_USAGE_SCOPED = 'view_usage_scoped';

    // Explicit scope actions
    case VIEW_ANY = 'view_any';
    case VIEW_OWN = 'view_own';
    case UPDATE_ANY = 'update_any';
    case UPDATE_OWN = 'update_own';
    case DELETE_ANY = 'delete_any';
    case DELETE_OWN = 'delete_own';

    // Other common actions
    case MANAGE = 'manage';
}
