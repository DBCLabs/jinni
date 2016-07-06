Jinni - A service to facilitate new connections over familiar social mediums.

This project is a PHP Laravel app, currently deployed to Amazon's Elastic Beanstalk.
A callback is sent to it from facebook whenever a facebook user sends a message to the Jinni facebook page. 
Facebook posts the callbacks to the /fbNewMessage route, which is handled by the
FbConversationCallbackController. The controller saves any relevant user info and then pairs the user with 
another available user (randomly for now). Eventually we'll allow users to send special commands,
for example "/next" which would cancel the current match and find a new one.

Since we went with the Laravel framework, a lot of the files in this project are currently unused and may never be needed,
but have been left in for now.
Relevant files/folders:
- app/Http/routes.php - specifies available routes and how to handle requests to them
- app/Http/Controllers/FbConversationCallbackController - contains business logic for routing messages and saving users
- app/Models - folder containing Laravel Model classes, which allow for querying the database table associated with the model,
 e.g. User model is used to retrieve/modify data in the Users table. These are basically wrapper classes around Laravel's query builder and will mostly be empty.
- database/migrations - contains migrations which should be run to create/update the database schema
- /storage/logs/laravel.log - log output
- tests - self explanatory, still need to write a lot of these
- .env.dev - environment variables for dev environments
- .env.local - environment variables for local environments
- composer.json - used to specify php dependencies
- phpunit.xml - unit test configurations

To download php dependencies: 
php composer.phar install

To run tests:
./vendor/bin/phpunit

To run app:
To test the app locally, I use homestead. Instructions for homestead can be found at https://laravel.com/docs/5.2/homestead
In your Homestead.yaml file, make sure the following lines are set (replace <path to jinni folder> with your local path):

folders:
    - map: <path to jinni folder>
      to: /home/vagrant/Code/jinni

sites:
    - map: homestead.app
      to: /home/vagrant/Code/jinni/public

databases:
    - jinnidb
    
If everything is set up correctly, you should be able to go to http://homestead.app and see a very basic homepage that says "Jinni"

To run migrations:
php artisan migrate   

If you are using homestead, you'll need to ssh into homestead to run migrations:
cd ~/Homestead
vagrant ssh

To test an actual callback against the server, there is a local_callback.sh script included that posts a curl with a valid 
callback to your local homestead server. You can check laravel.log to see log output 