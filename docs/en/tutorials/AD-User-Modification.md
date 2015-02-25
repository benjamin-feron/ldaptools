# AD User Modification
----------------------

Modifying an Active Directory user is really no different than modifying any other LDAP object, but there are a few
things to note. For example, using a few simple statements you can modify many of the user's properties typically seen
in the "Account" tab in the "AD Users and Computers" tool:

```php
use LdapTools\Object\LdapObjectType;

//...

// First get the user object via a repository.
$repository = $ldapManager->getRepository(LdapObjectType::USER);
$user = $repository->findOneByUsername('chad');

// Make sure the user account is set to enabled.
$user->setDisabled(false);
// Set their password to never expire.
$user->setPasswordNeverExpires(true);

try {
    $ldapManager->persist($user);
} catch (\Exception $e) {
    echo "Error modifying user! ".$e->getMessage();
}
```

With the above statement you have just the user account to be enabled and set the password to never expire.

## AD User Account Properties

This table contains many useful AD attributes you can toggle with a simple `true` or `false` value like above.

| Property Name  | Description |
| --------------- | -------------- |
| disabled | Disable (true) or enable (false) the account. |
| passwordIsReversible | Legacy AD setting. Should NOT be used. |
| passwordMustChange | Set that the user's password must change on the next login. |
| passwordNeverExpires | Set the user's password to never expire. |
| smartCardRequired | Set that a smart card is required for interactive login. |
| trustedForAllDelegation | Trust the user for delegation to any service (Kerberos). |
| trustedForAnyAuthDelegation | Delegate using any authentication protocol (When selected for delegation to specific services only). |