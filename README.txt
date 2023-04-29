Code Refactoring for:

app/Http/Controllers/BookingController.php
================================================================

As I observed, we are following repository/service pattern, therefore the function(distanceFeed) should be moved to the respective service class
i.e. DistanceRepository

Also, the function has a larger cognitive complexity value because of using too much else if,
I have broken down the functionality into two parts:
1. Used default laravel validation to check for the correct data types. and put required attribute for the JobId.
2. Used fall back (empty) values in the query, instead of encapsulating them into if else.



Code Refactoring for:

tests/app/Repository/UserRepository.php
================================================================
The class has one complex function that is performing multiple tasks:

The createOrUpdate method performs multiple responsibilities such as validation, creating, and updating
a user with additional responsibilities to create a Company, a Department, and a UserMeta depending on user role.
so, it is violating the single responsibility principle.

Also we can use the Laravel's mass assignment feature avoiding mulitple lines of assigning values to the model.


==================================================================
As a conclusion we can make the code more readable and easily debuggable by making use of
frameworks functionality and not writing function performing muliple tasks.

========================
I have added a test class in the Helpers' folder for the method willExpireAt.