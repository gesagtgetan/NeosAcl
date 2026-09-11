# Sandstorm.NeosAcl

Dynamic access control lists for Neos CMS 9: restrict editors to parts of the page tree
through roles that administrators manage in a backend module.

The development of the original package was sponsored by [ujamii](https://www.ujamii.com/)
and [queo](https://www.queo.de). Version 3 is a rewrite for the Neos 9 content repository;
the Neos 7 and 8 versions live on the 2.x branch.

Main features:

- Switch `Neos.Neos:RestrictedEditor` to an allowlist: after `./flow neosacl:setup` a
  restricted editor cannot edit anything until a dynamic role grants a subtree.
- Configure dynamic roles through the backend module "Dynamic Roles".
- A dynamic role grants editing of selected documents and their descendants, optionally
  limited to selected dimensions, and can make its members collaborators of shared
  workspaces.
- Permissions are purely additive: unrestricted editors and administrators keep editing everything.
- The document tree of the Neos UI shows a member of a dynamic role only the subtrees they may
  edit, plus the path leading there (setting `Sandstorm.NeosAcl.userInterface.hideUneditableDocuments`).
  Users without a dynamic role see the whole tree.

![listing](./Documentation/listing.png)

![edit](./Documentation/edit.png)

## Installation

```
composer require sandstorm/neosacl
./flow doctrine:migrate
./flow neosacl:setup
```

`neosacl:setup` tags every site node in the live workspace with the subtree tag
`neosacl-restricted`. Run it again after adding a site. Then log in as administrator and
open Administration > Dynamic Roles.

Users get a dynamic role like any other role, e.g. `./flow user:addrole jane Dynamic:Marketing`.

## How it works

Neos 9 authorizes node editing through subtree tags: an `EditNodePrivilege` target names a
tag, and a node may be edited only if one of the user's roles is granted a target that
matches a tag the node carries or inherits.

- `Policy.yaml` of this package defines the target `Sandstorm.NeosAcl:EditAllNodes` for the
  tag `neosacl-restricted` and grants it to `Neos.Neos:Editor` and `Neos.Neos:Administrator`.
  Because every site root carries that tag, every other role is denied.
- Each dynamic role `Dynamic:<name>` owns the tag `neosacl-<name>`. Saving the role in the
  module tags the selected node aggregates in the live workspace and the role is added to
  the policy at runtime (`PolicyService::configurationLoaded` signal) together with the
  target `Dynamic:<name>.EditNodes` matching its tag. Members may then edit those subtrees.
- Selected dimensions limit the tagging to the chosen dimension space points and their
  specializations. Without a selection the node is tagged in every dimension it covers.
- Selected shared workspaces get a `COLLABORATOR` assignment for the role, the same
  mechanism the Workspaces module uses.

- The tree filter overrides the FlowQuery operations `neosUiDefaultNodes` and
  `neosUiFilteredChildren` of the Neos UI and wraps its `ReloadNodesQueryHandler` (used after
  publishing and discarding), dropping documents the current user cannot edit unless an
  editable document lies below them. It is cosmetic: other documents stay reachable by URL,
  read only.

Subtree tags are content of the live workspace. An editor's personal workspace sees a
changed grant after its next rebase, which the Neos UI offers when live has changed.

`./flow neosacl:list` shows which node aggregates carry the restriction tag and the tags
of every dynamic role. `./flow neosacl:remove <name>` deletes a role from the command line.

## Changes compared to version 2 (Neos 7 and 8)

- The privilege levels "view", "view + edit" and "view + edit + create + delete" are gone.
  A dynamic role always grants editing, which in Neos 9 covers creating, removing, moving
  and tagging nodes below the selected documents. Restricting what the node tree shows is
  not possible with the Neos 9 authorization model.
- The per-node node type filter is gone.
- Dimension presets became dimension space points. Preset selections of existing roles are
  dropped by the migration; workspace and node selections are kept.
- Role names are limited to 28 characters of letters, digits and underscores and cannot be
  changed after creation, because the subtree tag derives from the name.
- The ACL inspector module, the cache frontends patching Flow's AOP caches and the React
  based editor were removed.

The PostgreSQL migration is untested.

## Development

Clone this package as `Sandstorm.NeosAcl` into `DistributionPackages/` of a Neos 9
installation, require `"sandstorm/neosacl": "@dev"` and run `composer update sandstorm/neosacl`.
Unit tests live in `Tests/Unit` and need no database.
