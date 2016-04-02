# NORM

## What the F is NORM

For starters, if you are one of those programmers who enjoy acronyms, NORM stands for __NORM Object-Relational Mapping__. And what does NORM means there? Well, __NORM Object-Relational Mapping__ you silly.

## What is NORM, really

As any other ORM technique, NORM gives you the capability to convert data between incompatible type systems in object-oriented programming languages. Which are these systems? I'm glad you asked!

First, in one hand, we have _Hummingbird_, the well-known and multiawarded PHP (check "What does PHP stands for?"" if you want to be amazed) framework.
In the other hand we have _MySQL_, the open-source relational database management system.

In a nutshell, what NORM does is create a logic layer inside Humminbird and on top of MySQL to easily create and manipulate objects. This objects allow you to access your database, providing a simple API for storing and retrieving data.

NORM will let you do all the cool stuff the cool kids are doing with php and a SQL database, but it is not alone...

### Entering ... (wait for it) ... CROOD

CROOD is our dumb way of saying CRUD. And what is CRUD? CRUD stands for __CR__eate, __U__pdate, __D__elete. So, in this fashion, CROOD lets you create, update and delete stuff from your database, but also other cool stuff like initialize object with conditions and fetch meta values.

If NORM were a programmer, CROOD would be his/her helpful cousin.

## Can i haz example?

Sure thing :smile:

For NORM to work, we have created a set of rules that are both practical and fun to use. These rules specify how to define and name your database tables and how to store your information.

Let's say you want a system to store movies. So, to create a movie table, bases on NORM rules (more about this in a sec), your table would look like this:

``` sql
CREATE TABLE `movie` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `release_year` int(11) NOT NULL,
  PRIMARY KEY (`id`)
);
```

If you have NORM setup in your project you will be able to do things like these:

``` php
$movie = new Movie();
$movie->name = 'Avengers: Infinity War: Part I';
$movie->release_year = 2018; //I'm so hyped about this movie
$movie->save();
```

Ta-da! You just created _Avengers: Infinity War: Part I_ in __4 lines__ of code (suck it Marvel).

## Can i haz adult example?

Okey dokey smarty pants.

Let's stick with the movies example.

In this example, you are creating a new iMDB because why not, and one of the functionalities in your site will be to filter all the movies by release year. For your filter, you can do something similiar to this:

``` php
$movies_by_year = Movies::getAllByReleaseYear( 1985 );
```

Boom! Now you have all the sweet movies from my birth year.

## Requirements

- Hummingbird Lite 2.0.3
- Hummingbird MVC 1.3+