# Organisation of the application / structure

The application is developed in the **MVC (Model/View/Controller)** architceture. This means that the individual application layers are structurally separated from each other and can therefore be expanded and changed separately.

The individual layers communicate with each other via methods and corresponding parameters so that any components can be added and changed without having to adapt the entire programme architecture, provided the nomenclature is adhered to.

The lowest (**model**) layer contains all the processes that manage access to the downstream database and provide the data. These classes are the model classes and can be found within the corresponding /model folder. 

They are named corresponding to their functionality - so user.php is the model class that deals with all USER database actions. Other models can be IDEA, COMMENTS etc. 

The content of the models is separated in such a way that they represent an image of the real functions. For example, there will be a ‘**User**’ model that contains all the functions (methods) for managing users. 

The next, higher layer can use the corresponding **model methods** (functions) to access data and perform operations without having knowledge of the underlying database structure or internal function of the model. 

An example of a (model) method could be a ‘**getUserName**’ function that determines the real name of a user for a specific user ID. 

The next higher layer are the **controllers**. They process the input coming front the frontend (VIEW), pass it on to the corresponding model class (i.e. user.php), receive the answer from the model and pass it on to the frontend. They ensure that all interactions with the ‘real world’, i.e. the ‘real’ user, for example, are checked, corrected and forwarded to the corresponding model.

The last and topmost layer is the **views**. These are the interfaces that are actually visible to the end user and with which the user ultimately interacts. Views have little technical functionality and consist primarily of templates (visual appearance).

The views pass their data (e.g. from a new registration form) to the corresponding controller, which processes the data and returns a ‘response’ to the view (e.g. successfully saved). The view then displays this response as a human-readable, visually appealing message.

## General information

The application is programmed entirely in **PHP 8.x** using a **MariaDB database**. The frontend (the individual views) is developed using the popular **React framework**. All technologies used are subject to the open source GPL-V3 licence.
