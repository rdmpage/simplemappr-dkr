Hi Rod,

Thanks for pushing on this. Answers inline below...

> On Thu, Mar 12, 2026 at 7:35 AM Roderic D. M. Page wrote:
> Hi David,
> 
> I’m still noodling around with this, it’s a great learning experience if nothing else.

> I have a coupe of questions…

> The first is how much do you want to be involved in this port? Do you want to be bugged for feedback and suggestions, or are you OK with it just happening? Before any release I’d obviously want to make sure the port makes clear where the inspirationa and code came from, etc.

Good questions. I am perfectly ok with it just happening, though happy to offer suggestions based on how I've seen SimpleMappr used. More on that below.
 
> The second question, which sort of depends on the first, is what would you consider to be a minimum viable product for a first release of the port? 

Although I've never been a user of SimpleMappr, I generally have a sense of what's necessary for those who do. One way to gauge this is to look at the outputs. For example: https://www.google.ca/search?q=simplemappr. In the past ~5-10 years, the majority of uses have enabled the raster-based shaded relief from Natural Earth, https://www.naturalearthdata.com/downloads/10m-raster-data/10m-cross-blend-hypso/. And, zoom/pan have been necessary when the layers are raster-based because the resolution of the outputs tends to be quite poor whereas users could indeed fiddle with zoom/pan offline if they downloaded an svg.
 
> - I’d be quite happy to not have authentication and storing maps, that just feels like a hassle, albeit presumably a useful feature.I think that’s a version 2 thing.

Agreed, as it was with SimpleMappr in the very beginning. Nonetheless, I see ~30 people with 100+ maps stored. The reason for storing is because of the option to add various point, region, or drawing layers; it's otherwise a nuisance to start from zero with every new session.
 
> - I haven’t added the region and shape drawing yet, but I think I can get them working.

Unlike the points, these have had minimal use on SimpleMappr. The region layer is a bit of a pain because it works by filtering as quasi-regex in the map file against one (or a few) columns the underlying shapefile data for which users have no mechanism to peek into its contents.
 
> - I have almost all the maps working.

> - Not sure about the zoom, cropping of maps, at a pinch people can do that themselves, but I may take a look at that.

If I were you and was to prioritize Claude's wrangling, I'd have it play here. In the absence of zoom, pan, and crop, the outputs won't have suitable resolution, especially for the rasters.
 
> - No Word or Powerpoint downloads yet.

Meh. Nothing lost there.
 
> - No API, I’ve no idea whether people use that

I have a running log of these, but I generally don't pay attention to it. Heaviest users/integrators are Tropicos and Yale. Larry Gall though retired, is the one to contact about Yale's integration, lawrence.gall@yale.edu. I have no idea who is left at Tropicos.
 
> I’m leaning towards the idea of having something usable working, standing up a server that could run for a while, and saying “if anyone wants more functionality, here’s the code”. In the past I would be annoyed by people saying “just hack the code” but now with Claude that’s actually not an unreasonable thing to say. That said, I would probably try and add stuff if people asked nicely.

Go for it!
 
> Lastly I am considering doing what sites like https://rogue-scholar.org/ do, which is use Ko-Fi to get a little support, e.g. https://ko-fi.com/rogue_scholar If the site runs on Hetzner then the cost is a couple of coffees a month.

If only it was the cost of coffee to build it ;)~

Best of luck!

David 