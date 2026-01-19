#!/usr/bin/env python3
"""Complete remaining updates to The Shoals.md"""

def complete_updates():
    with open('The Shoals.md', 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Update Chickasaws and Cherokees intro (needs exact match with 5 spaces after period)
    old1 = "Chickasaws and Cherokees\n     To examine native claims on the Shoals area is to look at the Chickasaws and Cherokees.     The Cherokees"
    new1 = "Chickasaws and Cherokees\n     To examine native claims on the Shoals area is to look at the Chickasaws and Cherokees, though understanding their presence requires acknowledging the complex web of Indigenous relationships that predated colonial boundaries.\n     The Cherokees"
    
    if old1 in content:
        content = content.replace(old1, new1)
        print("✓ Updated Chickasaws and Cherokees introduction")
    else:
        print("✗ Could not find Chickasaws/Cherokees text")
    
    # Update Colbert family section to emphasize Scottish heritage and leadership
    old2 = "Arrell M. Gibson, author of \"The Chickasaws,\" said that by 1800 mixed bloods dominated the Chickasaw tribe and the Colberts were the most dominant.     The rise of the Colberts can be attributed to James Logan Colbert, who, according to Trace historian Dawsan A. Phelps, was a Scotsman living among the Chickasaws as early as 1767."
    new2 = "The Scottish Colbert Family and Indigenous Leadership\n     Arrell M. Gibson, author of \"The Chickasaws,\" said that by 1800 mixed bloods dominated the Chickasaw tribe and the Colberts were the most dominant.     The rise of the Colberts can be attributed to James Logan Colbert, a Scottish trader who, according to Trace historian Dawsan A. Phelps, was living among the Chickasaws as early as 1767.     James Logan Colbert's integration into Chickasaw society through marriage to Chickasaw women created a family that would bridge two worlds, becoming powerful leaders within the Chickasaw nation while maintaining connections to Euro-American commerce and politics."
    
    if old2 in content:
        content = content.replace(old2, new2)
        print("✓ Updated Colbert family heritage section")
    else:
        print("✗ Could not find Colbert family text")
    
    # Update Colbert sons description
    old3 = "Colbert had at least five sons who all rose to prominence in the tribe.     The eldest"
    new3 = "Colbert had at least five sons who all rose to prominence in the tribe, becoming influential leaders who navigated the complex political landscape between Indigenous sovereignty and American expansion.\n     The eldest"
    
    if old3 in content:
        content = content.replace(old3, new3)
        print("✓ Updated Colbert sons description")
    else:
        print("✗ Could not find Colbert sons text")
    
    # Add ferry infrastructure context
    old4 = "He moved to the banks of the Tennessee in 1801 and operated a plantation and ferry on the site know as Georgetown until 1819.     Colbert became widely known"
    new4 = "He moved to the banks of the Tennessee in 1801 and operated a plantation and ferry on the site know as Georgetown until 1819.     George Colbert's ferry became essential infrastructure at the Shoals crossing, serving travelers on the Natchez Trace and facilitating the movement of people and goods through Chickasaw territory.\n     Colbert became widely known"
    
    if old4 in content:
        content = content.replace(old4, new4)
        print("✓ Added ferry infrastructure context")
    else:
        print("✗ Could not find ferry text")
    
    # Add Trail of Tears section
    old5 = "After 1820, he moved back to Mississippi and eventually went to Oklahoma in 1837 on the Chickasaw removal.\nLevi Colbert takes over"
    new5 = """After 1820, he moved back to Mississippi and eventually went to Oklahoma in 1837 on the Chickasaw removal.
The Trail of Tears and River Crossings
     The forced removal of Indigenous peoples from their ancestral lands, known as the Trail of Tears, brought thousands through the Shoals region in the 1830s. Multiple crossing points were used along the Tennessee River, from the traditional fords at the Shoals downstream to Waterloo, which became a major embarkation point.     Waterloo's deep water made it suitable for loading displaced Cherokee, Chickasaw, Choctaw, Creek, and other nations onto boats for the western journey.
     The Scottish-Chickasaw Colbert family, having once controlled the ferry crossings that facilitated peaceful trade and travel, witnessed their own people being forced westward along these same routes.     The river that had once been a meeting place for the Five Civilized Tribes became a corridor of sorrow, as tens of thousands were driven from their homes.     Many who crossed at Waterloo and the Shoals never completed the journey, succumbing to disease, exposure, and heartbreak along the way.
Levi Colbert takes over"""
    
    if old5 in content:
        content = content.replace(old5, new5)
        print("✓ Added Trail of Tears section")
    else:
        print("✗ Could not find Trail of Tears insertion point")
    
    # Update Donelson expedition
    old6 = "These may have been the first American families to use McFarland Bottoms as a campground.     After four months"
    new6 = "These may have been the first American families to use McFarland Bottoms as a campground—though Indigenous peoples had used these crossing points and camp sites for thousands of years.     The Shoals and areas downriver as far as Waterloo had long served as major crossing points, and Waterloo would later become a tragic departure point during the Trail of Tears.\n     After four months"
    
    if old6 in content:
        content = content.replace(old6, new6)
        print("✓ Updated Donelson expedition context")
    else:
        print("✗ Could not find Donelson text")
    
    # Update Waterloo section - fix typo "knows" to "known"
    old7 = "During the Indian removal, knows as the Trail of Tears, Waterloo was a major site for shipping American Indians west to the Oklahoma Territory. Dendy said"
    new7 = """During the Indian removal, known as the Trail of Tears, Waterloo was a major embarkation site for shipping American Indians west to the Oklahoma Territory. The town's deep-water port made it tragically suitable for loading displaced families onto boats.     Cherokee, Chickasaw, Choctaw, and Creek people—nations that had met peacefully at the Shoals for generations—were forcibly gathered here for deportation.     The suffering witnessed at Waterloo during the removals of the 1830s represents one of the darkest chapters in the region's history, transforming a place once associated with Indigenous trade and diplomacy into a point of departure for ethnic cleansing.
     Dendy said"""
    
    if old7 in content:
        content = content.replace(old7, new7)
        print("✓ Updated Waterloo Trail of Tears section")
    else:
        print("✗ Could not find Waterloo text")
    
    # Write updated content
    with open('The Shoals.md', 'w', encoding='utf-8') as f:
        f.write(content)
    
    print("\n✓ All updates complete!")

if __name__ == '__main__':
    complete_updates()
