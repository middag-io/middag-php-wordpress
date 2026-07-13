<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Security\Enum;

/**
 * Closed catalog of native WordPress core capabilities.
 *
 * A typed replacement for the magic capability strings plugins otherwise retype
 * (`'manage_options'`, `'edit_posts'`, ...): reference {@see self::ManageOptions}
 * and a typo fails at compile time instead of silently denying (or worse,
 * granting nothing). The backed value is the exact string WordPress expects.
 *
 * Cases are grouped by concern. Primitive capabilities are the ones stored on a
 * role and assignable via `add_cap()`. META capabilities (see {@see self::isMeta()})
 * are checked against a specific object id and resolved by `map_meta_cap()` at
 * runtime — never grant them to a role. Multisite/network caps are included but
 * are meaningful only on a network install; deprecated numeric `level_*` and the
 * legacy `edit_css`/`edit_files` caps are intentionally omitted.
 *
 * WooCommerce capabilities live in the sibling {@see WooCommerceCapability}.
 *
 * @api
 */
enum WpCapability: string implements CapabilityInterface
{
    // Themes (primitive)
    case SwitchThemes = 'switch_themes';

    case EditThemeOptions = 'edit_theme_options';

    case EditThemes = 'edit_themes';

    case InstallThemes = 'install_themes';

    case UpdateThemes = 'update_themes';

    case DeleteThemes = 'delete_themes';

    case ResumeThemes = 'resume_themes';

    // Plugins (primitive)
    case ActivatePlugins = 'activate_plugins';

    case InstallPlugins = 'install_plugins';

    case UpdatePlugins = 'update_plugins';

    case DeletePlugins = 'delete_plugins';

    case EditPlugins = 'edit_plugins';

    case ResumePlugins = 'resume_plugins';

    // Core & Updates & i18n (primitive)
    case UpdateCore = 'update_core';

    case InstallLanguages = 'install_languages';

    case UpdateLanguages = 'update_languages';

    case ViewSiteHealthChecks = 'view_site_health_checks';

    // Posts (primitive)
    case EditPosts = 'edit_posts';

    case EditOthersPosts = 'edit_others_posts';

    case EditPublishedPosts = 'edit_published_posts';

    case EditPrivatePosts = 'edit_private_posts';

    case PublishPosts = 'publish_posts';

    case ReadPrivatePosts = 'read_private_posts';

    case DeletePosts = 'delete_posts';

    case DeleteOthersPosts = 'delete_others_posts';

    case DeletePublishedPosts = 'delete_published_posts';

    case DeletePrivatePosts = 'delete_private_posts';

    // Pages (primitive)
    case EditPages = 'edit_pages';

    case EditOthersPages = 'edit_others_pages';

    case EditPublishedPages = 'edit_published_pages';

    case EditPrivatePages = 'edit_private_pages';

    case PublishPages = 'publish_pages';

    case ReadPrivatePages = 'read_private_pages';

    case DeletePages = 'delete_pages';

    case DeleteOthersPages = 'delete_others_pages';

    case DeletePublishedPages = 'delete_published_pages';

    case DeletePrivatePages = 'delete_private_pages';

    // Users (primitive)
    case ListUsers = 'list_users';

    case CreateUsers = 'create_users';

    case EditUsers = 'edit_users';

    case DeleteUsers = 'delete_users';

    case PromoteUsers = 'promote_users';

    case RemoveUsers = 'remove_users';

    case AddUsers = 'add_users';

    // Files & Uploads (primitive)
    case UploadFiles = 'upload_files';

    case UnfilteredUpload = 'unfiltered_upload';

    // Options, Dashboard & Customize (primitive)
    case ManageOptions = 'manage_options';

    case EditDashboard = 'edit_dashboard';

    case Customize = 'customize';

    case DeleteSite = 'delete_site';

    // Categories, Terms & Links (primitive)
    case ManageCategories = 'manage_categories';

    case ManageLinks = 'manage_links';

    // Comments (primitive)
    case ModerateComments = 'moderate_comments';

    // Content & HTML (primitive)
    case UnfilteredHtml = 'unfiltered_html';

    // Import / Export (primitive)
    case Import = 'import';

    case Export = 'export';

    // Reading (primitive)
    case Read = 'read';

    // Multisite / Network Super Admin (primitive)
    case CreateSites = 'create_sites';

    case DeleteSites = 'delete_sites';

    case ManageSites = 'manage_sites';

    case ManageNetwork = 'manage_network';

    case ManageNetworkUsers = 'manage_network_users';

    case ManageNetworkThemes = 'manage_network_themes';

    case ManageNetworkPlugins = 'manage_network_plugins';

    case ManageNetworkOptions = 'manage_network_options';

    case UploadPlugins = 'upload_plugins';

    case UploadThemes = 'upload_themes';

    case UpgradeNetwork = 'upgrade_network';

    case SetupNetwork = 'setup_network';

    // Posts (meta)
    case EditPost = 'edit_post';

    case ReadPost = 'read_post';

    case DeletePost = 'delete_post';

    case PublishPost = 'publish_post';

    case EditPostMeta = 'edit_post_meta';

    case AddPostMeta = 'add_post_meta';

    case DeletePostMeta = 'delete_post_meta';

    // Pages (meta)
    case EditPage = 'edit_page';

    case ReadPage = 'read_page';

    case DeletePage = 'delete_page';

    // Comments (meta)
    case EditComment = 'edit_comment';

    case EditCommentMeta = 'edit_comment_meta';

    case AddCommentMeta = 'add_comment_meta';

    case DeleteCommentMeta = 'delete_comment_meta';

    // Terms (meta)
    case EditTerm = 'edit_term';

    case DeleteTerm = 'delete_term';

    case AssignTerm = 'assign_term';

    case EditTermMeta = 'edit_term_meta';

    case AddTermMeta = 'add_term_meta';

    case DeleteTermMeta = 'delete_term_meta';

    // Users (meta)
    case EditUser = 'edit_user';

    case DeleteUser = 'delete_user';

    case PromoteUser = 'promote_user';

    case RemoveUser = 'remove_user';

    case EditUserMeta = 'edit_user_meta';

    case AddUserMeta = 'add_user_meta';

    case DeleteUserMeta = 'delete_user_meta';

    // Application Passwords (meta)
    case CreateAppPassword = 'create_app_password';

    case ListAppPasswords = 'list_app_passwords';

    case ReadAppPassword = 'read_app_password';

    case EditAppPassword = 'edit_app_password';

    case DeleteAppPasswords = 'delete_app_passwords';

    case DeleteAppPassword = 'delete_app_password';

    // Privacy (GDPR) (meta)
    case ManagePrivacyOptions = 'manage_privacy_options';

    case ExportOthersPersonalData = 'export_others_personal_data';

    case EraseOthersPersonalData = 'erase_others_personal_data';

    public function toString(): string
    {
        return $this->value;
    }

    public function isMeta(): bool
    {
        return match ($this) {
            self::EditPost, self::ReadPost, self::DeletePost, self::PublishPost,
            self::EditPostMeta, self::AddPostMeta, self::DeletePostMeta, self::EditPage,
            self::ReadPage, self::DeletePage, self::EditComment, self::EditCommentMeta,
            self::AddCommentMeta, self::DeleteCommentMeta, self::EditTerm, self::DeleteTerm,
            self::AssignTerm, self::EditTermMeta, self::AddTermMeta, self::DeleteTermMeta,
            self::EditUser, self::DeleteUser, self::PromoteUser, self::RemoveUser,
            self::EditUserMeta, self::AddUserMeta, self::DeleteUserMeta, self::CreateAppPassword,
            self::ListAppPasswords, self::ReadAppPassword, self::EditAppPassword, self::DeleteAppPasswords,
            self::DeleteAppPassword, self::ManagePrivacyOptions, self::ExportOthersPersonalData, self::EraseOthersPersonalData,
            // customize → edit_theme_options and delete_site → manage_options are
            // pure map_meta_cap remaps with no object id (WP capabilities.php):
            // granting them to a role is a silent no-op, so they are meta too.
            self::Customize, self::DeleteSite => true,
            default => false,
        };
    }
}
