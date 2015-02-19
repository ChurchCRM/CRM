Two electronic donation processors are currenly supported by ChurchInfo: Vanco and Authorize.NET.
Of the two, Vanco appears to be the better choice because their rates are better and ChurchInfo
is able to use transparent redirection to store account information directly on the Vanco server.
When using Authorize.NET the ChurchInfo server must be certified as PCI-compliant because the
donor account information is stored in the ChurchInfo database.

Vanco
Contact Vanco to establish an account.  They will provide the credentials which must be plugged
into the file Include/VancoConfig.php. Use Admin->Edit General Settings to change 
sElectronicTransactionProcessor to Vanco if necessary (this is the default value)

Authorize.NET
Contact Authorize.NET to establish an account.  The will provide the credentials which must be
plugged into the file Include/AuthorizeNetConfig.php.  Use Admin->Edit General Settings to change
sElectronicTransactionProcessor to AuthorizeNet (this is not the default value).
