# Image optimiser filter
The image optimiser filter is intended to solve the issue of high resolution images slowing down (and blocking) page
loads.

Features:

* Resize images that are greater than a maximum width and preserve aspect ratios and original images.
* Resizes images in all filterable text - course content, user profile description, etc.
* Allow for delayed loading of images (load when visible) with the option of specifying how many images should be loaded
immediately before images are placeheld and loaded when in the view port and the page has fully loaded (eliminates
blocking for other resources, e.g. javascript in the footer).

Example scenarios:

* Course designer doesn't know how to resize images prior to upload and uploads a 6 mega pixel image when they don't
require this resolution.
The filter solves this issue by automatically resizing images to a specified maximum width (aspect ratios are preserved).

* Course designer uploads 100s of images into a course label
The filter can solve this issue by place holding images and making them load only when scrolled into the viewport.

* Students using mobile data plans are finding their course page to be sluggish due to unnecessarily large images
uploaded to their course.
The filter can solve this issue by both placeholding images until scrolled into the viewport and then resizing the
image which is served to the user.

## Configuration

For the filter to work, it must be enabled via Site administration / Plugins / Filters / Manage filters.

The filter settings area available via Site administration / Plugins / Filters / Image optimiser.

By default, the optimiser both place holds (load when visible) and resizes (maximum image width) to 800px.

## Videos

[Load on visible feature](https://youtu.be/TC5iyoUYw1A)

[Non destructive resizing](https://youtu.be/JRdLumnr_rk)

[Mobile page load speed WITHOUT image optimiser](https://youtu.be/3JYRfjxNTig)

[Mobile page load speed WITH image optimiser](https://youtu.be/cU0xYC6v0GY)

Note, the page is ready in half the time when the filter is enabled!

## Copyright

(c) Guy Thomas 2017

## Licence

http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

## Author

Developed by Guy Thomas.
