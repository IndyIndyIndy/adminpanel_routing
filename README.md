# TYPO3 Extension "adminpanel_routing"
Debug Routing information in the adminpanel in TYPO3 9.5+.

## What does it do?

This extensions shows debugging information related to Routing API.
It catches each generated uri during a page request and lists it in the "Debug -> Routing Information" module in the 
admin panel.

For example, for "enhanced" uris, it can show you the "original parameters", their internal "deflated" state and the 
"resolved" parameters, which could then, for example have been enhanced by replacing its uid with a slug by a 
PersistedAliasMapper.

This is the first working version of the extension and likely still has a lot of room for improvements. Testing and 
feature requests/ideas are very much appreciated.

## Images

![Screenshot](/Images/example1.png)

![Screenshot](/Images/example2.png)

## Requirements

Currently only supports 9.5 LTS.

## Installation

### Installation with composer

`composer require christianessl/adminpanel_routing`. 

## TODOS
- Replace ugly XClass (An Event Listener would be required inside the PageRouter class in the core)
- Replace Signal/Slots with PSR-14 Event Listeners (which require TYPO3 10)
- Also list aspects in the adminpanel
- Also show debug information for PageRouter::matchRequest()?
- Create tests
