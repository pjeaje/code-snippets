$tags = get_tags(array( 
    'exclude' => get_queried_object_id(), 
)); 

$tagList = array(); 

foreach($tags as $tag) 
{ 
    $tagList[] = '<a href="'.get_tag_link($tag->term_id).'">'.$tag->name.'</a>'; 

} 
echo implode(', ', $tagList);
