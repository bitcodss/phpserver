
(function($) {
  //util function to check if an element has a scrollbar present
  jQuery.fn.hasScrollBar = function(direction)
  {
    if (direction == 'vertical')
    {
      return this.get(0).scrollHeight > this.innerHeight();
    }
    else if (direction == 'horizontal')
    {
      return this.get(0).scrollWidth > this.innerWidth();
    }
    return false;

  }
});