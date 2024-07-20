## Features

- **Multiple Ranks**: Players can have multiple ranks.
- **Permissions**: Each rank can have its own set of permissions.
- **Chat Colors**: Players can have a chat color associated with their rank.
- **Tags**: Players can have a tag associated with their rank.
- **MySQL Integration**: All data is stored in a MySQL database.
- **FormAPI Integration**: Provides a user-friendly form interface for commands.

## Session

- getRanks() : array: Returns an array of the player’s ranks.
- addRank(string $rank) : void: Adds a rank to the player’s ranks. If the rank already exists or doesn’t exist in the rank manager, it will not be added.
- removeRank(string $rank) : void: Removes a rank from the player’s ranks.
- getPermissions() : array: Returns an array of the player’s permissions.
- addPermission(string $permission) : void: Adds a permission to the player’s permissions.
- removePermission(string $permission) : void: Removes a permission from the player’s permissions.
- getChatColor() : string: Returns the player’s chat color.
- setChatColor(string $chatColor) : void: Sets the player’s chat color.
- getTag() : string: Returns the player’s tag.
- setTag(string $tag) : void: Sets the player’s tag.

## RankManager

- getRanks() : array: Returns an array of all the ranks on the server.
- rankExists(string $rank) : bool: Checks if a rank exists.
- createRank(string $rank) : void: Creates a new rank. If the rank already exists, it will not be created.
- deleteRank(string $rank) : void: Deletes a rank. If the rank doesn’t exist, it will not be deleted.
