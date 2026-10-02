<?php
/*
System: modules, actions, settings, notifications, custom fields and the
reference lists the rest of the API points at.

Nothing here is writable through the API. Changing modules, actions or
settings by HTTP would let a key holder grant itself permissions, so these
resources are read-only by design.
*/

return [

'modules' => [
    'title' => 'Modules',
    'group' => 'System',
    'scope' => 'system',
    'module' => 'System Admin',
    'action' => 'Manage Modules',
    'table' => 'tawasulModule',
    'primaryKey' => 'tawasulModuleID',
    'description' => 'Installed modules, their version and whether they are active.',
    'select' => "SELECT tawasulModule.tawasulModuleID, tawasulModule.name, tawasulModule.description, tawasulModule.entryURL, tawasulModule.type, tawasulModule.active, tawasulModule.category, tawasulModule.version, tawasulModule.author, tawasulModule.url FROM tawasulModule",
    'filters' => ['active' => 'tawasulModule.active', 'type' => 'tawasulModule.type', 'category' => 'tawasulModule.category'],
    'search' => ['tawasulModule.name', 'tawasulModule.description'],
    'defaultSort' => 'tawasulModule.category, tawasulModule.name',
],

'actions' => [
    'title' => 'Actions',
    'group' => 'System',
    'scope' => 'system',
    'module' => 'System Admin',
    'action' => 'Manage Modules',
    'table' => 'tawasulAction',
    'primaryKey' => 'tawasulActionID',
    'description' => 'Module actions, the unit TawasulOS grants permissions against.',
    'select' => "SELECT tawasulAction.tawasulActionID, tawasulAction.tawasulModuleID, tawasulAction.name, tawasulAction.precedence, tawasulAction.category, tawasulAction.description, tawasulAction.URLList, tawasulAction.entryURL, tawasulAction.menuShow, tawasulModule.name AS moduleName FROM tawasulAction JOIN tawasulModule ON (tawasulAction.tawasulModuleID=tawasulModule.tawasulModuleID)",
    'filters' => ['tawasulModuleID' => 'tawasulAction.tawasulModuleID', 'category' => 'tawasulAction.category', 'menuShow' => 'tawasulAction.menuShow'],
    'search' => ['tawasulAction.name', 'tawasulAction.description'],
    'defaultSort' => 'tawasulModule.name, tawasulAction.name',
],

'permissions' => [
    'title' => 'Permissions',
    'group' => 'System',
    'scope' => 'system',
    'module' => 'User Admin',
    'action' => 'Manage Permissions',
    'table' => 'tawasulPermission',
    'primaryKey' => 'permissionID',
    'description' => 'Which roles hold which action, the raw permission matrix.',
    'select' => "SELECT tawasulPermission.permissionID, tawasulPermission.tawasulRoleID, tawasulPermission.tawasulActionID, tawasulRole.name AS roleName, tawasulRole.category AS roleCategory, tawasulAction.name AS actionName, tawasulModule.name AS moduleName FROM tawasulPermission JOIN tawasulRole ON (tawasulPermission.tawasulRoleID=tawasulRole.tawasulRoleID) JOIN tawasulAction ON (tawasulPermission.tawasulActionID=tawasulAction.tawasulActionID) JOIN tawasulModule ON (tawasulAction.tawasulModuleID=tawasulModule.tawasulModuleID)",
    'filters' => ['tawasulRoleID' => 'tawasulPermission.tawasulRoleID', 'tawasulActionID' => 'tawasulPermission.tawasulActionID', 'moduleName' => 'tawasulModule.name'],
    'defaultSort' => 'tawasulRole.name, tawasulModule.name, tawasulAction.name',
],

'settings' => [
    'title' => 'Settings',
    'group' => 'System',
    'scope' => 'system',
    'module' => 'System Admin',
    'action' => 'Third Party Settings',
    'table' => 'tawasulSetting',
    'primaryKey' => 'tawasulSettingID',
    'description' => 'TawasulOS settings by scope. Values known to hold credentials are redacted.',
    'select' => "SELECT tawasulSetting.tawasulSettingID, tawasulSetting.scope, tawasulSetting.name, tawasulSetting.nameDisplay, tawasulSetting.description, tawasulSetting.value FROM tawasulSetting",
    'where' => ["tawasulSetting.scope NOT IN ('System Admin','TawasulCore')", "tawasulSetting.name NOT LIKE '%ecret%'", "tawasulSetting.name NOT LIKE '%assword%'", "tawasulSetting.name NOT LIKE '%apiKey%'", "tawasulSetting.name NOT LIKE '%Token%'"],
    'filters' => ['scope' => 'tawasulSetting.scope', 'name' => 'tawasulSetting.name'],
    'search' => ['tawasulSetting.name', 'tawasulSetting.nameDisplay'],
    'defaultSort' => 'tawasulSetting.scope, tawasulSetting.name',
],

'notifications' => [
    'title' => 'Notifications',
    'group' => 'System',
    'scope' => 'notifications',
    'module' => null,
    'action' => null,
    'table' => 'tawasulNotification',
    'primaryKey' => 'tawasulNotificationID',
    'description' => 'In-app notifications raised for a person.',
    'select' => "SELECT tawasulNotification.tawasulNotificationID, tawasulNotification.tawasulPersonID, tawasulNotification.status, tawasulNotification.tawasulModuleID, tawasulNotification.count, tawasulNotification.text, tawasulNotification.actionLink, tawasulNotification.timestamp, tawasulModule.name AS moduleName FROM tawasulNotification LEFT JOIN tawasulModule ON (tawasulNotification.tawasulModuleID=tawasulModule.tawasulModuleID)",
    'filters' => ['tawasulPersonID' => 'tawasulNotification.tawasulPersonID', 'status' => 'tawasulNotification.status'],
    'defaultSort' => 'tawasulNotification.timestamp DESC',
    'writable' => ['tawasulPersonID', 'status', 'tawasulModuleID', 'text', 'actionLink'],
    'required' => ['tawasulPersonID', 'text', 'actionLink'],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'custom-fields' => [
    'title' => 'Custom Fields',
    'group' => 'System',
    'scope' => 'system',
    'module' => 'System Admin',
    'action' => 'Manage Custom Fields',
    'table' => 'tawasulCustomField',
    'primaryKey' => 'tawasulCustomFieldID',
    'description' => 'Custom field definitions, needed to interpret the "fields" column on other resources.',
    'select' => "SELECT tawasulCustomField.tawasulCustomFieldID, tawasulCustomField.context, tawasulCustomField.name, tawasulCustomField.active, tawasulCustomField.description, tawasulCustomField.type, tawasulCustomField.options, tawasulCustomField.required, tawasulCustomField.heading, tawasulCustomField.sequenceNumber FROM tawasulCustomField",
    'filters' => ['context' => 'tawasulCustomField.context', 'active' => 'tawasulCustomField.active', 'type' => 'tawasulCustomField.type'],
    'search' => ['tawasulCustomField.name'],
    'defaultSort' => 'tawasulCustomField.context, tawasulCustomField.sequenceNumber',
],

'outcomes' => [
    'title' => 'Outcomes',
    'group' => 'System',
    'scope' => 'curriculum',
    'module' => 'Planner',
    'action' => 'Manage Outcomes',
    'table' => 'tawasulOutcome',
    'primaryKey' => 'tawasulOutcomeID',
    'description' => 'Learning outcomes available to units and lessons.',
    'select' => "SELECT tawasulOutcome.tawasulOutcomeID, tawasulOutcome.name, tawasulOutcome.nameShort, tawasulOutcome.category, tawasulOutcome.description, tawasulOutcome.active, tawasulOutcome.scope, tawasulOutcome.tawasulDepartmentID, tawasulOutcome.tawasulYearGroupIDList FROM tawasulOutcome",
    'filters' => ['active' => 'tawasulOutcome.active', 'scope' => 'tawasulOutcome.scope', 'tawasulDepartmentID' => 'tawasulOutcome.tawasulDepartmentID', 'category' => 'tawasulOutcome.category'],
    'search' => ['tawasulOutcome.name', 'tawasulOutcome.nameShort'],
    'defaultSort' => 'tawasulOutcome.category, tawasulOutcome.name',
],

'rubrics' => [
    'title' => 'Rubrics',
    'group' => 'System',
    'scope' => 'curriculum',
    'module' => 'Rubrics',
    'action' => 'Manage Rubrics',
    'table' => 'tawasulRubric',
    'primaryKey' => 'tawasulRubricID',
    'description' => 'Assessment rubrics.',
    'select' => "SELECT tawasulRubric.tawasulRubricID, tawasulRubric.name, tawasulRubric.category, tawasulRubric.description, tawasulRubric.active, tawasulRubric.scope, tawasulRubric.tawasulDepartmentID, tawasulRubric.tawasulYearGroupIDList, tawasulRubric.tawasulScaleID FROM tawasulRubric",
    'filters' => ['active' => 'tawasulRubric.active', 'scope' => 'tawasulRubric.scope', 'tawasulDepartmentID' => 'tawasulRubric.tawasulDepartmentID'],
    'search' => ['tawasulRubric.name'],
    'defaultSort' => 'tawasulRubric.name',
],

'api-logs' => [
    'title' => 'API Request Log',
    'group' => 'System',
    'scope' => 'apilogs',
    'module' => 'TawasulCore',
    'action' => 'View Request Log',
    'table' => 'tos_api_log',
    'primaryKey' => 'tos_api_logID',
    'description' => 'This add-on own request log: every API call, its key, status and duration.',
    'select' => "SELECT tos_api_log.tos_api_logID, tos_api_log.tos_api_keyID, tos_api_log.tawasulPersonID, tos_api_log.method, tos_api_log.endpoint, tos_api_log.resource, tos_api_log.statusCode, tos_api_log.durationMS, tos_api_log.message, tos_api_log.ipAddress, tos_api_log.timestamp, tos_api_key.name AS keyName FROM tos_api_log LEFT JOIN tos_api_key ON (tos_api_log.tos_api_keyID=tos_api_key.tos_api_keyID)",
    'filters' => ['tos_api_keyID' => 'tos_api_log.tos_api_keyID', 'statusCode' => 'tos_api_log.statusCode', 'method' => 'tos_api_log.method', 'resource' => 'tos_api_log.resource', 'tawasulPersonID' => 'tos_api_log.tawasulPersonID'],
    'search' => ['tos_api_log.endpoint', 'tos_api_log.ipAddress'],
    'sort' => ['timestamp' => 'tos_api_log.timestamp', 'durationMS' => 'tos_api_log.durationMS'],
    'defaultSort' => 'tos_api_log.timestamp DESC',
],

'notification-templates' => [
    'title' => 'Notification Templates',
    'group' => 'System',
    'scope' => 'notifications',
    'module' => 'Notification Center',
    'action' => 'Manage Notification Templates',
    'table' => 'notificationTemplate',
    'primaryKey' => 'notificationTemplateID',
    'description' => 'Custom templates that control the title and body of notifications sent when system events fire.',
    'select' => "SELECT notificationTemplate.notificationTemplateID, notificationTemplate.name, notificationTemplate.eventName, notificationTemplate.title, notificationTemplate.body, notificationTemplate.moduleName, notificationTemplate.actionName, notificationTemplate.scope, notificationTemplate.active, notificationTemplate.tawasulPersonIDCreator, notificationTemplate.timestampCreated, notificationTemplate.timestampUpdated FROM notificationTemplate",
    'filters' => ['active' => 'notificationTemplate.active', 'eventName' => 'notificationTemplate.eventName', 'moduleName' => 'notificationTemplate.moduleName'],
    'search' => ['notificationTemplate.name', 'notificationTemplate.eventName', 'notificationTemplate.title'],
    'sort' => ['name' => 'notificationTemplate.name', 'timestampCreated' => 'notificationTemplate.timestampCreated'],
    'defaultSort' => 'notificationTemplate.moduleName, notificationTemplate.name',
    'writable' => ['name', 'eventName', 'title', 'body', 'moduleName', 'actionName', 'scope', 'active', 'tawasulPersonIDCreator'],
    'required' => ['name', 'eventName', 'title', 'moduleName', 'actionName'],
    'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
],

'notification-events' => [
    'title' => 'Notification Events',
    'group' => 'System',
    'scope' => 'notifications',
    'module' => null,
    'action' => null,
    'table' => 'tawasulNotificationEvent',
    'primaryKey' => 'tawasulNotificationEventID',
    'description' => 'System events that can generate notifications, and the module/action/scope they target.',
    'select' => "SELECT tawasulNotificationEvent.* FROM tawasulNotificationEvent",
    'filters' => ['active' => 'tawasulNotificationEvent.active', 'moduleName' => 'tawasulNotificationEvent.moduleName', 'type' => 'tawasulNotificationEvent.type'],
    'search' => ['tawasulNotificationEvent.event', 'tawasulNotificationEvent.moduleName'],
    'sort' => ['event' => 'tawasulNotificationEvent.event'],
    'defaultSort' => 'tawasulNotificationEvent.moduleName, tawasulNotificationEvent.event',
],

'notification-listeners' => [
    'title' => 'Notification Listeners',
    'group' => 'System',
    'scope' => 'notifications',
    'module' => null,
    'action' => null,
    'table' => 'tawasulNotificationListener',
    'primaryKey' => 'tawasulNotificationListenerID',
    'description' => 'Who is listening to which notification events, with scope-based delivery rules.',
    'select' => "SELECT tawasulNotificationListener.*, e.event AS eventName, e.moduleName, e.actionName FROM tawasulNotificationListener LEFT JOIN tawasulNotificationEvent e ON (tawasulNotificationListener.tawasulNotificationEventID=e.tawasulNotificationEventID)",
    'filters' => ['tawasulNotificationEventID' => 'tawasulNotificationListener.tawasulNotificationEventID', 'tawasulPersonID' => 'tawasulNotificationListener.tawasulPersonID', 'scopeType' => 'tawasulNotificationListener.scopeType'],
    'defaultSort' => 'tawasulNotificationListener.tawasulNotificationEventID, tawasulNotificationListener.tawasulPersonID',
],

// The generated definition for this table is wrong: it names the table "i18n"
// while using the real "tawasuli18n" columns, so every request failed with
// "Base table or view not found: i18n". Curated definitions are merged after
// the generated ones, so this entry replaces it.
'i18ns' => [
    'title' => 'Languages',
    'group' => 'System',
    'scope' => 'i18ns',
    'module' => 'System Admin',
    'action' => 'Manage Languages',
    'table' => 'tawasuli18n',
    'primaryKey' => 'tawasuli18nID',
    'description' => 'The languages this TawasulOS can present its interface in, with the date formats each expects and whether it reads right to left.',
    'select' => 'SELECT tawasuli18n.* FROM tawasuli18n',
    'filters' => [
        'active' => 'tawasuli18n.active',
        'installed' => 'tawasuli18n.installed',
        'systemDefault' => 'tawasuli18n.systemDefault',
        'rtl' => 'tawasuli18n.rtl',
        'code' => 'tawasuli18n.code',
    ],
    'search' => ['tawasuli18n.name', 'tawasuli18n.code'],
    'sort' => ['name' => 'tawasuli18n.name', 'code' => 'tawasuli18n.code'],
    'defaultSort' => 'tawasuli18n.name',
    // Editing installed languages would change what the installer offers, so
    // this stays read-only like the rest of the System group.
    'methods' => ['GET'],
    'writable' => [],
    'required' => [],
],

];
