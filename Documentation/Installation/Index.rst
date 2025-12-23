.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _administration:

Installation/updating
=====================

Target group: **Administrators**


.. _read_before_installing_or_updating:

Read before installing or updating!
-----------------------------------

Before installing this extension or updating to a new major release, you should **always**
read the sections "Upgrade procedure" and "Important changes" in the :ref:`ChangeLog <changelog>`.


.. _installation:

Installation
------------

To install the extension, perform the following steps:

#. Go to the Extension Manager
#. Install the extension
#. Include the static template :ref:`*Media Gallery (fs_media_gallery)* <users_manual>`

To use the latest version from the `code repository <https://bitbucket.org/franssaris/fs_media_gallery>`_ install the extension from command line:

.. code:: bash

    cd /your/path/to/typo3root/
    git clone git@bitbucket.org:franssaris/fs_media_gallery.git --single-branch --branch master --depth 1 typo3conf/ext/fs_media_gallery
    ./typo3/cli_dispatch.phpsh extbase extension:install fs_media_gallery

